<?php

namespace App\Services;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\ServiceOrderStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Notifications\GeneralNotification;
use Illuminate\Support\Facades\DB;

/**
 * Thu ngân: lập hóa đơn (TD-01), thu tiền (TD-02), hủy/hoàn (TD-04), tiền đặt cọc online (BN-10).
 */
class BillingService
{
    /** TD-01: tổng hợp phí khám + phí CLS + tiền thuốc của lượt khám. */
    public function createForVisit(Visit $visit, ?User $by = null): Invoice
    {
        $invoice = $visit->invoice ?? Invoice::create([
            'visit_id' => $visit->id,
            'patient_id' => $visit->patient_id,
            'status' => InvoiceStatus::Unpaid,
            'created_by' => $by?->id,
        ]);

        return $this->rebuild($invoice);
    }

    /** Tính lại các dòng hóa đơn (chỉ khi chưa thanh toán). */
    public function rebuild(Invoice $invoice): Invoice
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            return $invoice;
        }

        $visit = $invoice->visit()->with(['doctor', 'serviceOrders.service', 'prescription.items.medicine', 'appointment'])->first();

        DB::transaction(function () use ($invoice, $visit) {
            $invoice->items()->delete();

            $invoice->items()->create([
                'item_type' => 'exam',
                'description' => 'Phí khám - '.$visit->doctor->display_name,
                'quantity' => 1,
                'unit_price' => $visit->doctor->consultation_fee,
                'amount' => $visit->doctor->consultation_fee,
                'reference_type' => Visit::class,
                'reference_id' => $visit->id,
            ]);

            foreach ($visit->serviceOrders->where('status', '!=', ServiceOrderStatus::Cancelled) as $order) {
                $invoice->items()->create([
                    'item_type' => 'service',
                    'description' => $order->service->name,
                    'quantity' => 1,
                    'unit_price' => $order->price,
                    'amount' => $order->price,
                    'reference_type' => $order::class,
                    'reference_id' => $order->id,
                ]);
            }

            foreach ($visit->prescription?->items ?? [] as $item) {
                $invoice->items()->create([
                    'item_type' => 'medicine',
                    'description' => $item->medicine->fullName().' ('.$item->medicine->unit.')',
                    'quantity' => $item->quantity,
                    'unit_price' => $item->unit_price,
                    'amount' => $item->amount,
                    'reference_type' => $item::class,
                    'reference_id' => $item->id,
                ]);
            }

            $subtotal = (int) $invoice->items()->sum('amount');
            $deposit = min($subtotal, (int) ($visit->appointment?->prepaid_amount ?? 0));
            $total = max(0, $subtotal - (int) $invoice->discount - $deposit);

            $invoice->update([
                'subtotal' => $subtotal,
                'deposit_deducted' => $deposit,
                'total' => $total,
            ]);

            // Đã trả trước đủ (đặt cọc online) thì hóa đơn tự chuyển "Đã thanh toán"
            if ($total === 0) {
                $invoice->update(['status' => InvoiceStatus::Paid, 'paid_at' => now()]);
            }
        });

        return $invoice->refresh();
    }

    /** TD-02: thu tiền tại quầy (thu đủ số còn phải trả). */
    public function pay(Invoice $invoice, PaymentMethod $method, User $cashier, ?string $transactionNo = null, ?string $note = null): Payment
    {
        $payment = DB::transaction(function () use ($invoice, $method, $cashier, $transactionNo, $note) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if ($invoice->status !== InvoiceStatus::Unpaid) {
                throw new BusinessRuleException('Hóa đơn '.$invoice->code.' đang ở trạng thái "'.$invoice->status->label().'".');
            }

            $amount = $invoice->amountDue();
            $payment = Payment::create([
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'type' => 'payment',
                'method' => $method,
                'amount' => $amount,
                'status' => PaymentStatus::Success,
                'transaction_no' => $transactionNo,
                'note' => $note,
                'received_by' => $cashier->id,
                'paid_at' => now(),
            ]);

            $invoice->update([
                'status' => InvoiceStatus::Paid,
                'paid_amount' => $invoice->paid_amount + $amount,
                'payment_method' => $method,
                'cashier_id' => $cashier->id,
                'paid_at' => now(),
            ]);

            return $payment;
        });

        $this->notifyPaid($invoice->refresh());

        return $payment;
    }

    /** TD-04: hủy hóa đơn chưa thu tiền. */
    public function cancel(Invoice $invoice, string $reason, User $by): void
    {
        if ($invoice->status !== InvoiceStatus::Unpaid) {
            throw new BusinessRuleException('Chỉ hủy được hóa đơn chưa thanh toán. Hóa đơn đã thu tiền dùng chức năng hoàn tiền.');
        }

        $invoice->update([
            'status' => InvoiceStatus::Cancelled,
            'cancel_reason' => $reason,
            'cancelled_by' => $by->id,
            'cancelled_at' => now(),
        ]);
    }

    /** TD-04: hoàn tiền hóa đơn đã thu (bắt buộc lý do, lưu người thực hiện). */
    public function refund(Invoice $invoice, string $reason, User $by): Payment
    {
        return DB::transaction(function () use ($invoice, $reason, $by) {
            $invoice = Invoice::whereKey($invoice->id)->lockForUpdate()->first();

            if ($invoice->status !== InvoiceStatus::Paid) {
                throw new BusinessRuleException('Chỉ hoàn tiền được hóa đơn đã thanh toán.');
            }

            $refund = Payment::create([
                'invoice_id' => $invoice->id,
                'patient_id' => $invoice->patient_id,
                'type' => 'refund',
                'method' => $invoice->payment_method ?? PaymentMethod::Cash,
                'amount' => $invoice->paid_amount,
                'status' => PaymentStatus::Success,
                'note' => $reason,
                'received_by' => $by->id,
                'paid_at' => now(),
            ]);

            $invoice->update([
                'status' => InvoiceStatus::Refunded,
                'cancel_reason' => $reason,
                'cancelled_by' => $by->id,
                'cancelled_at' => now(),
            ]);

            return $refund;
        });
    }

    /**
     * HT-08: ghi nhận thanh toán online thành công (gọi từ IPN/return đã xác thực chữ ký).
     * Idempotent: gọi lại nhiều lần không cộng tiền hai lần.
     */
    public function completeOnlinePayment(Payment $payment, ?string $transactionNo, array $payload = []): bool
    {
        return DB::transaction(function () use ($payment, $transactionNo, $payload) {
            $payment = Payment::whereKey($payment->id)->lockForUpdate()->first();

            if ($payment->status === PaymentStatus::Success) {
                return false;
            }

            $payment->update([
                'status' => PaymentStatus::Success,
                'transaction_no' => $transactionNo,
                'gateway_payload' => $payload,
                'paid_at' => now(),
            ]);

            if ($payment->appointment_id) {
                $appointment = Appointment::whereKey($payment->appointment_id)->lockForUpdate()->first();
                $appointment->increment('prepaid_amount', $payment->amount);

                if ($appointment->visit?->invoice) {
                    $this->rebuild($appointment->visit->invoice);
                }
            }

            if ($payment->invoice_id) {
                $invoice = Invoice::whereKey($payment->invoice_id)->lockForUpdate()->first();
                $paid = $invoice->paid_amount + $payment->amount;
                $invoice->update([
                    'paid_amount' => $paid,
                    'payment_method' => $payment->method ?? PaymentMethod::Vnpay,
                    'status' => $paid >= $invoice->total ? InvoiceStatus::Paid : $invoice->status,
                    'paid_at' => $paid >= $invoice->total ? now() : null,
                ]);
            }

            return true;
        });
    }

    public function failOnlinePayment(Payment $payment, array $payload = []): void
    {
        if ($payment->status === PaymentStatus::Pending) {
            $payment->update(['status' => PaymentStatus::Failed, 'gateway_payload' => $payload]);
        }
    }

    protected function notifyPaid(Invoice $invoice): void
    {
        $owner = $invoice->patient?->user;

        if ($owner) {
            rescue(fn () => $owner->notify(new GeneralNotification(
                'Thanh toán thành công',
                'Hóa đơn '.$invoice->code.' - '.money($invoice->paid_amount).' đã được thanh toán.',
                route('account.invoices.index'),
            )));
        }
    }
}
