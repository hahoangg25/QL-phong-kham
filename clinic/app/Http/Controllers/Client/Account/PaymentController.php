<?php

namespace App\Http\Controllers\Client\Account;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Billing\Invoice;
use App\Models\Billing\Payment;
use App\Services\MomoService;
use App\Services\VnpayService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/** BN-10: thanh toán online phí khám/đặt cọc hoặc hóa đơn qua VNPay, ví MoMo (môi trường test) hoặc cổng giả lập. */
class PaymentController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        protected VnpayService $vnpay,
        protected MomoService $momo,
    ) {}

    public function appointment(Request $request, Appointment $appointment)
    {
        $this->authorizeOwner($appointment);

        if (! in_array($appointment->status, AppointmentStatus::active(), true)) {
            return back()->with('error', 'Lịch hẹn ở trạng thái "'.$appointment->status->label().'" không thể thanh toán.');
        }

        $amount = $appointment->payableOnlineAmount();
        if ($amount <= 0) {
            return back()->with('info', 'Lịch hẹn này đã được thanh toán trước đủ.');
        }

        $payment = Payment::create([
            'appointment_id' => $appointment->id,
            'patient_id' => $appointment->patient_id,
            'type' => 'deposit',
            'method' => $this->gateway($request),
            'amount' => $amount,
            'status' => PaymentStatus::Pending,
            'note' => 'Thanh toán trước phí khám lịch hẹn '.$appointment->code,
        ]);

        return $this->redirectToGateway($request, $payment, 'Thanh toan lich hen '.$appointment->code);
    }

    public function invoice(Request $request, Invoice $invoice)
    {
        $this->authorizeOwner($invoice);

        if ($invoice->status !== InvoiceStatus::Unpaid || $invoice->amountDue() <= 0) {
            return back()->with('info', 'Hóa đơn này không cần thanh toán.');
        }

        $payment = Payment::create([
            'invoice_id' => $invoice->id,
            'patient_id' => $invoice->patient_id,
            'type' => 'payment',
            'method' => $this->gateway($request),
            'amount' => $invoice->amountDue(),
            'status' => PaymentStatus::Pending,
            'note' => 'Thanh toán online hóa đơn '.$invoice->code,
        ]);

        return $this->redirectToGateway($request, $payment, 'Thanh toan hoa don '.$invoice->code);
    }

    /** Cổng bệnh nhân chọn: vnpay (mặc định) hoặc momo. */
    protected function gateway(Request $request): PaymentMethod
    {
        $method = PaymentMethod::tryFrom((string) $request->input('gateway', PaymentMethod::Vnpay->value));

        return $method && $method->isOnline() ? $method : PaymentMethod::Vnpay;
    }

    protected function redirectToGateway(Request $request, Payment $payment, string $orderInfo)
    {
        if ($payment->method === PaymentMethod::Momo) {
            if (! $this->momo->isConfigured()) {
                return redirect()->route('payment.simulate', $payment);
            }

            try {
                return redirect()->away($this->momo->createPaymentUrl(
                    $payment,
                    route('payment.momo.return'),
                    route('payment.momo.ipn'),
                    $orderInfo,
                ));
            } catch (BusinessRuleException $e) {
                $payment->update(['status' => PaymentStatus::Failed, 'gateway_payload' => ['gateway' => 'momo', 'error' => $e->getMessage()]]);

                return back()->with('error', $e->getMessage());
            }
        }

        if ($this->vnpay->isConfigured()) {
            return redirect()->away($this->vnpay->createPaymentUrl(
                $payment,
                route('payment.vnpay.return'),
                $request->ip() ?? '127.0.0.1',
                Str::ascii($orderInfo),
            ));
        }

        return redirect()->route('payment.simulate', $payment);
    }
}
