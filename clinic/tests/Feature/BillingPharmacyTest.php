<?php

namespace Tests\Feature;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\PrescriptionStatus;
use App\Enums\VisitStatus;
use App\Models\Catalog\Doctor;
use App\Models\Billing\Invoice;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Clinical\Patient;
use App\Models\Billing\Payment;
use App\Models\Clinical\Prescription;
use App\Models\Pharmacy\StockReceipt;
use App\Models\Pharmacy\StockTransaction;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Services\BillingService;
use Tests\TestCase;

class BillingPharmacyTest extends TestCase
{
    /** Lượt khám đã hoàn thành có đơn 500 viên Panadol (vượt lô đầu 480 viên để kiểm tra FEFO) và hóa đơn. */
    protected function makeCompletedVisit(int $quantity = 500): array
    {
        $doctor = Doctor::with('user')->first();
        $patient = Patient::whereNotNull('user_id')->first();

        $visit = Visit::create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'room_id' => $doctor->room_id,
            'visit_date' => today(),
            'queue_number' => Visit::where('doctor_id', $doctor->id)->whereDate('visit_date', today())->max('queue_number') + 1,
            'queue_sort' => now(),
            'status' => VisitStatus::Completed,
            'type' => 'walk_in',
            'completed_at' => now(),
            'locked_at' => now(),
        ]);

        $prescription = Prescription::create([
            'visit_id' => $visit->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'status' => PrescriptionStatus::Pending,
        ]);

        $medicine = Medicine::where('code', 'T001')->first();
        $prescription->items()->create([
            'medicine_id' => $medicine->id,
            'quantity' => $quantity,
            'dosage' => '1 viên/lần',
            'usage' => 'Uống sau ăn',
            'days' => 5,
            'unit_price' => $medicine->price,
            'amount' => $quantity * $medicine->price,
        ]);

        $invoice = app(BillingService::class)->createForVisit($visit, $doctor->user);

        return [$visit, $prescription->fresh(), $invoice->fresh(), $medicine];
    }

    public function test_cashier_lists_shows_and_collects_payment(): void
    {
        [, , $invoice] = $this->makeCompletedVisit();
        $this->loginAs('thungan@phongkham.test');

        $this->assertSame(InvoiceStatus::Unpaid, $invoice->status);
        $this->assertGreaterThan(0, $invoice->total);

        $this->get(route('staff.cashier.index'))->assertOk()->assertSee($invoice->code);
        $this->get(route('staff.cashier.index', ['tab' => 'all', 'q' => $invoice->code]))->assertOk()->assertSee($invoice->code);
        $this->get(route('staff.cashier.show', $invoice))->assertOk()->assertSee('Thu tiền')->assertSee('Phí khám');

        // Chuyển khoản bắt buộc mã giao dịch
        $this->post(route('staff.cashier.pay', $invoice), ['method' => 'transfer'])->assertSessionHasErrors('transaction_no');

        $this->post(route('staff.cashier.pay', $invoice), ['method' => 'cash', 'note' => 'Thu tại quầy'])
            ->assertRedirect(route('staff.cashier.show', $invoice))
            ->assertSessionHas('success');

        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Paid, $invoice->status);
        $this->assertSame($invoice->total, $invoice->paid_amount);
        $this->assertSame(PaymentMethod::Cash, $invoice->payment_method);
        $this->assertTrue(Payment::where('invoice_id', $invoice->id)->where('type', 'payment')
            ->where('status', PaymentStatus::Success->value)->where('amount', $invoice->total)->exists());

        // Thu lần hai bị từ chối
        $this->post(route('staff.cashier.pay', $invoice), ['method' => 'cash'])->assertSessionHas('error');

        $this->get(route('staff.cashier.print', $invoice))->assertOk()->assertSee('Biên lai thu tiền')->assertSee('đồng');
        $this->get(route('staff.cashier.report'))->assertOk()->assertSee($invoice->code)->assertSee(money($invoice->total));
        $this->get(route('staff.cashier.report', ['from' => today()->subDays(7)->toDateString(), 'to' => today()->toDateString()]))->assertOk();
        $this->get(route('staff.cashier.index', ['tab' => 'paid']))->assertOk()->assertSee($invoice->code);
    }

    public function test_cashier_cancels_unpaid_and_refunds_paid_invoice(): void
    {
        [, , $invoice] = $this->makeCompletedVisit();
        $cashier = $this->loginAs('thungan@phongkham.test');

        $this->post(route('staff.cashier.cancel', $invoice), [])->assertSessionHasErrors('reason');
        $this->post(route('staff.cashier.cancel', $invoice), ['reason' => 'Lập nhầm'])->assertSessionHas('success');
        $invoice->refresh();
        $this->assertSame(InvoiceStatus::Cancelled, $invoice->status);
        $this->assertSame($cashier->id, $invoice->cancelled_by);

        // Hóa đơn thứ hai: thu rồi hoàn tiền
        [, , $second] = $this->makeCompletedVisit(10);
        app(BillingService::class)->pay($second, PaymentMethod::Cash, $cashier);
        $this->post(route('staff.cashier.refund', $second->fresh()), ['reason' => 'Bệnh nhân đổi ý'])->assertSessionHas('success');
        $second->refresh();
        $this->assertSame(InvoiceStatus::Refunded, $second->status);
        $this->assertTrue(Payment::where('invoice_id', $second->id)->where('type', 'refund')->where('amount', $second->paid_amount)->exists());

        // Không hoàn tiền hóa đơn đã hủy
        $this->post(route('staff.cashier.refund', $invoice), ['reason' => 'x'])->assertSessionHas('error');
        $this->get(route('staff.cashier.index', ['tab' => 'closed']))->assertOk()->assertSee($second->code);
        $this->get(route('staff.cashier.report'))->assertOk()->assertSee('Hoàn tiền');
    }

    public function test_pharmacist_dispenses_paid_prescription_using_fefo(): void
    {
        [, $prescription, $invoice, $medicine] = $this->makeCompletedVisit(500);
        $this->loginAs('duocsi@phongkham.test');

        $batches = MedicineBatch::where('medicine_id', $medicine->id)->orderBy('expiry_date')->get();
        [$early, $late] = [$batches[0], $batches[1]];
        // Cố định tồn lô sớm để đơn 500 viên buộc phải lấy sang lô muộn (không phụ thuộc số liệu seeder)
        $early->update(['remaining' => 480]);
        $this->assertTrue($early->expiry_date->lt($late->expiry_date));
        $this->assertLessThan(500, $early->remaining);

        // Chưa thanh toán: không được cấp phát
        $this->get(route('staff.pharmacy.index', ['tab' => 'unpaid']))->assertOk()->assertSee($prescription->code);
        $this->get(route('staff.pharmacy.show', $prescription))->assertOk()->assertSee('chưa được thanh toán');
        $this->post(route('staff.pharmacy.dispense', $prescription))->assertSessionHas('error');
        $this->assertSame(PrescriptionStatus::Pending, $prescription->fresh()->status);

        app(BillingService::class)->pay($invoice, PaymentMethod::Cash, User::where('email', 'thungan@phongkham.test')->first());

        $this->get(route('staff.pharmacy.index'))->assertOk()->assertSee($prescription->code);
        $this->get(route('staff.pharmacy.show', $prescription))->assertOk()->assertSee($early->batch_no)->assertSee($late->batch_no);

        $earlyQty = $early->remaining;
        $lateQty = $late->remaining;
        $this->post(route('staff.pharmacy.dispense', $prescription))
            ->assertRedirect(route('staff.pharmacy.show', $prescription))
            ->assertSessionHas('success');

        $prescription->refresh();
        $this->assertSame(PrescriptionStatus::Dispensed, $prescription->status);
        $this->assertNotNull($prescription->dispensed_at);
        $this->assertSame(0, $early->fresh()->remaining);
        $this->assertSame($lateQty - (500 - $earlyQty), $late->fresh()->remaining);
        $this->assertSame(2, StockTransaction::where('reference_type', Prescription::class)
            ->where('reference_id', $prescription->id)->where('type', 'dispense')->count());
        $this->assertSame(-500, (int) StockTransaction::where('reference_id', $prescription->id)->where('type', 'dispense')->sum('quantity'));

        // Đơn đã cấp không cấp lại / không sửa (BR-10)
        $this->post(route('staff.pharmacy.dispense', $prescription))->assertSessionHas('error');
        $this->get(route('staff.pharmacy.index', ['tab' => 'dispensed']))->assertOk()->assertSee($prescription->code);
        $this->get(route('staff.pharmacy.show', $prescription))->assertOk()->assertSee('BR-10');
    }

    public function test_medicine_crud(): void
    {
        $this->loginAs('duocsi@phongkham.test');

        $this->get(route('staff.medicines.index'))->assertOk()->assertSee('Acemuc');
        $this->get(route('staff.medicines.index', ['q' => 'Panadol']))->assertOk()->assertSee('Panadol');
        $this->get(route('staff.medicines.index', ['q' => 'Paracetamol']))->assertOk()->assertSee('Hapacol');
        $this->get(route('staff.medicines.create'))->assertOk();

        $payload = [
            'code' => 'T999', 'name' => 'Thuốc thử', 'active_ingredient' => 'Hoạt chất X', 'strength' => '10mg',
            'dosage_form' => 'Viên nén', 'unit' => 'Viên', 'route' => 'Uống', 'price' => 1500, 'min_stock' => 10,
            'manufacturer' => 'Test', 'is_active' => 1,
        ];
        $this->post(route('staff.medicines.store'), $payload)->assertRedirect(route('staff.medicines.index'));
        $medicine = Medicine::where('code', 'T999')->firstOrFail();
        $this->assertTrue($medicine->is_active);

        // Mã trùng bị từ chối
        $this->post(route('staff.medicines.store'), $payload)->assertSessionHasErrors('code');

        $this->get(route('staff.medicines.edit', $medicine))->assertOk()->assertSee('Thuốc thử');
        $this->put(route('staff.medicines.update', $medicine), array_merge($payload, ['price' => 2000]))->assertRedirect(route('staff.medicines.index'));
        $this->assertSame(2000, $medicine->fresh()->price);

        $this->put(route('staff.medicines.update', $medicine), ['toggle_active' => 1])->assertSessionHas('success');
        $this->assertFalse($medicine->fresh()->is_active);

        $this->delete(route('staff.medicines.destroy', $medicine))->assertSessionHas('success');
        $this->assertNull(Medicine::find($medicine->id));

        // Thuốc đã có lô: chuyển ngừng sử dụng thay vì xóa
        $used = Medicine::where('code', 'T001')->first();
        $this->delete(route('staff.medicines.destroy', $used))->assertSessionHas('warning');
        $this->assertNotNull($used->fresh());
        $this->assertFalse($used->fresh()->is_active);
    }

    public function test_stock_receipt_creates_batches(): void
    {
        $this->loginAs('duocsi@phongkham.test');
        [$a, $b] = Medicine::orderBy('id')->take(2)->get();

        $this->get(route('staff.receipts.index'))->assertOk();
        $this->get(route('staff.receipts.create'))->assertOk()->assertSee($a->name);

        // Hạn dùng đã qua bị từ chối
        $this->post(route('staff.receipts.store'), [
            'supplier' => 'NCC', 'received_date' => today()->toDateString(),
            'items' => [['medicine_id' => $a->id, 'batch_no' => 'X1', 'expiry_date' => today()->subDay()->toDateString(), 'quantity' => 5, 'import_price' => 100]],
        ])->assertSessionHasErrors('items.0.expiry_date');

        $response = $this->post(route('staff.receipts.store'), [
            'supplier' => 'Công ty Dược Test',
            'received_date' => today()->toDateString(),
            'note' => 'Nhập bổ sung',
            'items' => [
                ['medicine_id' => $a->id, 'batch_no' => 'NEW-A', 'expiry_date' => today()->addYear()->toDateString(), 'quantity' => 100, 'import_price' => 700],
                ['medicine_id' => $b->id, 'batch_no' => 'NEW-B', 'expiry_date' => today()->addMonths(8)->toDateString(), 'quantity' => 50, 'import_price' => 1800],
            ],
        ]);

        $receipt = StockReceipt::where('supplier', 'Công ty Dược Test')->firstOrFail();
        $response->assertRedirect(route('staff.receipts.show', $receipt));
        $this->assertSame(2, $receipt->batches()->count());
        $this->assertSame(100 * 700 + 50 * 1800, (int) $receipt->total_amount);
        $this->assertTrue(MedicineBatch::where('batch_no', 'NEW-A')->where('remaining', 100)->exists());
        $this->assertSame(2, StockTransaction::where('reference_type', StockReceipt::class)->where('reference_id', $receipt->id)->where('type', 'import')->count());

        $this->get(route('staff.receipts.show', $receipt))->assertOk()->assertSee('NEW-A')->assertSee('NEW-B');
        $this->get(route('staff.receipts.index', ['q' => 'Dược Test']))->assertOk()->assertSee($receipt->code);
    }

    public function test_inventory_alerts_dispose_adjust_and_report(): void
    {
        $this->loginAs('duocsi@phongkham.test');

        // Seeder: Ventolin dưới định mức, Telfast có lô sắp hết hạn, Tobrex có lô đã hết hạn
        $this->get(route('staff.inventory.index'))->assertOk()
            ->assertSee('Ventolin')->assertSee('Telfast')->assertSee('L34X');
        $this->get(route('staff.inventory.index', ['filter' => 'low']))->assertOk()->assertSee('Ventolin');

        $medicine = Medicine::where('code', 'T034')->first();
        $this->get(route('staff.inventory.show', $medicine))->assertOk()->assertSee('L34X');

        $expired = MedicineBatch::where('batch_no', 'L34X')->first();
        $this->post(route('staff.inventory.dispose', $expired), ['quantity' => $expired->remaining + 1, 'reason' => 'Hết hạn'])->assertSessionHas('error');
        $this->post(route('staff.inventory.dispose', $expired), ['quantity' => $expired->remaining, 'reason' => 'Hết hạn'])
            ->assertRedirect(route('staff.inventory.show', $medicine->id));
        $this->assertSame(0, $expired->fresh()->remaining);
        $this->assertTrue(StockTransaction::where('medicine_batch_id', $expired->id)->where('type', 'dispose')->exists());

        $batch = MedicineBatch::where('medicine_id', $medicine->id)->where('remaining', '>', 0)->first();
        $before = $batch->remaining;
        $this->post(route('staff.inventory.adjust', $batch), ['actual' => $before - 3, 'reason' => 'Kiểm kê cuối tháng'])->assertSessionHas('success');
        $this->assertSame($before - 3, $batch->fresh()->remaining);
        $this->assertTrue(StockTransaction::where('medicine_batch_id', $batch->id)->where('type', 'adjust')->where('quantity', -3)->exists());

        $this->get(route('staff.inventory.show', $medicine))->assertOk()->assertSee('Xuất hủy')->assertSee('Điều chỉnh kiểm kê');

        $from = today()->subDays(60)->toDateString();
        $to = today()->toDateString();
        $this->get(route('staff.inventory.report', compact('from', 'to')))->assertOk()->assertSee('Tobrex')->assertSee('Tồn cuối kỳ');

        $csv = $this->get(route('staff.inventory.report', compact('from', 'to') + ['export' => 'csv']));
        $csv->assertOk();
        $this->assertStringContainsString('text/csv', $csv->headers->get('Content-Type'));
        $content = $csv->streamedContent();
        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);
        $this->assertStringContainsString('Tồn cuối kỳ', $content);
        $this->assertStringContainsString('Tobrex', $content);
    }

    public function test_receptionist_cannot_access_billing_and_pharmacy(): void
    {
        [, $prescription, $invoice, $medicine] = $this->makeCompletedVisit();
        $this->loginAs('letan@phongkham.test');

        foreach ([
            route('staff.cashier.index'), route('staff.cashier.report'), route('staff.cashier.show', $invoice),
            route('staff.pharmacy.index'), route('staff.pharmacy.show', $prescription),
            route('staff.medicines.index'), route('staff.receipts.index'), route('staff.receipts.create'),
            route('staff.inventory.index'), route('staff.inventory.show', $medicine), route('staff.inventory.report'),
        ] as $url) {
            $this->get($url)->assertForbidden();
        }

        $this->post(route('staff.cashier.pay', $invoice), ['method' => 'cash'])->assertForbidden();
        $this->post(route('staff.pharmacy.dispense', $prescription))->assertForbidden();
        $this->assertSame(InvoiceStatus::Unpaid, Invoice::find($invoice->id)->status);
    }
}
