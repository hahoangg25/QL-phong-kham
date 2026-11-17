<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Patient;
use App\Models\Billing\Payment;
use App\Models\Scheduling\SlotHold;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use App\Services\MomoService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class BookingFlowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['clinic.show_otp' => true]);
    }

    protected function bookableSlot(?int $excludeId = null, ?int $doctorId = null): TimeSlot
    {
        return TimeSlot::with('doctor')
            ->where('is_active', true)
            ->where('starts_at', '>', now()->addHours(3))
            ->when($excludeId, fn ($q) => $q->where('id', '!=', $excludeId))
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->orderBy('starts_at')
            ->firstOrFail();
    }

    protected function demoPatient(): Patient
    {
        return Patient::where('full_name', 'Nguyễn Văn An')->firstOrFail();
    }

    protected function bookForDemo(): Appointment
    {
        $staff = User::where('email', 'letan@phongkham.test')->first();

        return app(BookingService::class)->bookByStaff($staff, $this->demoPatient(), $this->bookableSlot(), 'Đau đầu');
    }

    public function test_slot_endpoint_supports_doctor_specialty_and_days_modes(): void
    {
        $slot = $this->bookableSlot();
        $date = $slot->date->toDateString();

        $this->getJson(route('slots.index', ['doctor_id' => $slot->doctor_id, 'date' => $date]))
            ->assertOk()
            ->assertJsonPath('date', $date)
            ->assertJsonStructure(['slots' => [['id', 'time', 'end', 'available', 'capacity', 'bookable', 'period']]]);

        $this->getJson(route('slots.index', ['specialty_id' => $slot->doctor->specialty_id, 'date' => $date]))
            ->assertOk()
            ->assertJsonStructure(['doctors' => [['id', 'name', 'avatar', 'fee', 'rating', 'slots']]]);

        $days = $this->getJson(route('slots.index', ['doctor_id' => $slot->doctor_id, 'from' => today()->toDateString(), 'days' => 7]))
            ->assertOk()
            ->assertJsonStructure(['days' => [['date', 'dow', 'day', 'available']]])
            ->json('days');
        $this->assertCount(7, $days);

        $this->getJson(route('slots.index'))->assertStatus(422);
    }

    public function test_symptom_suggestion_returns_matching_specialty(): void
    {
        $res = $this->getJson(route('symptoms.suggest', ['q' => 'đau họng, sổ mũi, nghẹt mũi']))->assertOk();
        $this->assertSame('tai-mui-hong', $res->json('suggestions.0.slug'));

        $this->getJson(route('symptoms.suggest', ['q' => 'a']))->assertOk()->assertJsonCount(0, 'suggestions');
    }

    public function test_full_online_booking_flow_with_otp(): void
    {
        $this->loginAs('benhnhan@phongkham.test');
        $patient = $this->demoPatient();
        $slot = $this->bookableSlot();

        $this->get(route('account.booking.create'))->assertOk()->assertSee('Chọn chuyên khoa');
        $this->get(route('account.booking.create', ['doctor_id' => $slot->doctor_id]))->assertOk();

        $res = $this->post(route('account.booking.store'), [
            'patient_id' => $patient->id,
            'time_slot_id' => $slot->id,
            'symptoms' => 'Sốt nhẹ, ho khan',
        ]);
        $hold = SlotHold::firstOrFail();
        $res->assertRedirect(route('account.booking.verify', $hold));
        $otp = session('dev_otp');
        $this->assertNotEmpty($otp);

        $this->get(route('account.booking.verify', $hold))->assertOk()->assertSee('Nhập mã OTP');

        // Sai OTP
        $this->post(route('account.booking.confirm', $hold), ['otp' => $otp === '111111' ? '222222' : '111111'])
            ->assertSessionHas('error');
        $this->assertDatabaseCount('appointments', 0);

        $this->post(route('account.booking.confirm', $hold), ['otp' => $otp])->assertRedirect();
        $appointment = Appointment::firstOrFail();
        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame($patient->id, $appointment->patient_id);
        $this->assertSame(1, $slot->fresh()->booked_count);
        $this->assertDatabaseCount('slot_holds', 0);

        $this->get(route('account.appointments.show', $appointment))->assertOk()->assertSee($appointment->code)->assertSee('<svg', false);
    }

    public function test_cannot_book_profile_of_another_account(): void
    {
        $this->loginAs('benhnhan@phongkham.test');
        $other = User::where('email', 'benhnhan1@phongkham.test')->first()->patients()->first();

        $this->post(route('account.booking.store'), [
            'patient_id' => $other->id,
            'time_slot_id' => $this->bookableSlot()->id,
        ])->assertForbidden();
    }

    public function test_release_and_expired_hold(): void
    {
        $this->loginAs('benhnhan@phongkham.test');
        $this->post(route('account.booking.store'), ['patient_id' => $this->demoPatient()->id, 'time_slot_id' => $this->bookableSlot()->id]);
        $hold = SlotHold::firstOrFail();

        $this->post(route('account.booking.resend', $hold))->assertRedirect(); // có thể báo "đợi 30 giây"

        $hold->update(['expires_at' => now()->subMinute()]);
        $this->get(route('account.booking.verify', $hold))->assertRedirect(route('account.booking.create'));
        $this->assertDatabaseCount('slot_holds', 0);

        $this->post(route('account.booking.store'), ['patient_id' => $this->demoPatient()->id, 'time_slot_id' => $this->bookableSlot()->id]);
        $hold = SlotHold::firstOrFail();
        $this->delete(route('account.booking.release', $hold))->assertRedirect(route('account.booking.create'));
        $this->assertDatabaseCount('slot_holds', 0);
    }

    public function test_patient_can_cancel_appointment(): void
    {
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');
        $slot = $appointment->timeSlot;
        $this->assertSame(1, $slot->fresh()->booked_count);

        $this->post(route('account.appointments.cancel', $appointment), ['reason' => 'Bận việc'])
            ->assertRedirect(route('account.appointments.show', $appointment));

        $this->assertSame(AppointmentStatus::Cancelled, $appointment->fresh()->status);
        $this->assertSame(0, $slot->fresh()->booked_count);
    }

    public function test_patient_can_reschedule_with_otp(): void
    {
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');

        $this->get(route('account.appointments.reschedule', $appointment))->assertOk()->assertSee('Đổi lịch hẹn');

        $newSlot = $this->bookableSlot($appointment->time_slot_id, $appointment->doctor_id);
        $this->post(route('account.appointments.reschedule.store', $appointment), ['time_slot_id' => $newSlot->id]);
        $hold = SlotHold::firstOrFail();
        $this->assertSame($appointment->id, $hold->reschedule_from_id);
        $otp = session('dev_otp');

        $this->get(route('account.booking.verify', $hold))->assertOk()->assertSee('Đổi từ lịch');
        $this->post(route('account.booking.confirm', $hold), ['otp' => $otp])->assertRedirect();

        $old = $appointment->fresh();
        $this->assertSame(AppointmentStatus::Rescheduled, $old->status);
        $this->assertNotNull($old->rescheduled_to_id);
        $new = Appointment::find($old->rescheduled_to_id);
        $this->assertSame($newSlot->id, $new->time_slot_id);
        $this->assertSame(0, $appointment->timeSlot->fresh()->booked_count);
    }

    public function test_br09_cannot_access_other_accounts_appointment(): void
    {
        $staff = User::where('email', 'letan@phongkham.test')->first();
        $otherPatient = User::where('email', 'benhnhan1@phongkham.test')->first()->patients()->first();
        $appointment = app(BookingService::class)->bookByStaff($staff, $otherPatient, $this->bookableSlot());

        $this->loginAs('benhnhan@phongkham.test');
        $this->get(route('account.appointments.show', $appointment))->assertForbidden();
        $this->getJson(route('account.appointments.queue', $appointment))->assertForbidden();
        $this->post(route('account.appointments.cancel', $appointment))->assertForbidden();
        $this->get(route('account.appointments.reschedule', $appointment))->assertForbidden();
        $this->post(route('account.appointments.pay', $appointment))->assertForbidden();
        $this->assertSame(AppointmentStatus::Confirmed, $appointment->fresh()->status);
    }

    public function test_simulated_payment_increments_prepaid_amount(): void
    {
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');
        $amount = $appointment->payableOnlineAmount();
        $this->assertGreaterThan(0, $amount);

        $this->post(route('account.appointments.pay', $appointment))->assertRedirect();
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentStatus::Pending, $payment->status);
        $this->assertSame($amount, (int) $payment->amount);

        $this->get(route('payment.simulate', $payment))->assertOk()->assertSee('Thanh toán thành công');
        $this->post(route('payment.simulate.process', $payment), ['action' => 'success'])->assertOk()->assertSee('Thanh toán thành công');

        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame($amount, (int) $appointment->fresh()->prepaid_amount);

        // Đã trả đủ thì không tạo thêm giao dịch
        $this->post(route('account.appointments.pay', $appointment))->assertSessionHas('info');
        $this->assertDatabaseCount('payments', 1);
    }

    public function test_simulated_payment_cancel_marks_failed(): void
    {
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');
        $this->post(route('account.appointments.pay', $appointment));
        $payment = Payment::firstOrFail();

        $this->post(route('payment.simulate.process', $payment), ['action' => 'cancel'])->assertOk();
        $this->assertSame(PaymentStatus::Failed, $payment->fresh()->status);
        $this->assertSame(0, (int) $appointment->fresh()->prepaid_amount);
    }

    public function test_vnpay_ipn_rejects_invalid_signature(): void
    {
        $this->getJson(route('payment.vnpay.ipn', ['vnp_TxnRef' => 'X', 'vnp_Amount' => 100, 'vnp_SecureHash' => 'abc']))
            ->assertOk()
            ->assertJson(['RspCode' => '97']);
    }

    public function test_vnpay_ipn_valid_signature_confirms_payment_once(): void
    {
        config(['clinic.vnpay.tmn_code' => 'TESTTMN', 'clinic.vnpay.hash_secret' => 'TESTSECRET']);
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');

        $res = $this->post(route('account.appointments.pay', $appointment));
        $payment = Payment::firstOrFail();
        $this->assertStringContainsString('vnpayment.vn', $res->headers->get('Location'));

        $params = [
            'vnp_Amount' => $payment->amount * 100,
            'vnp_ResponseCode' => '00',
            'vnp_TransactionStatus' => '00',
            'vnp_TransactionNo' => '14000001',
            'vnp_TxnRef' => $payment->code,
            'vnp_TmnCode' => 'TESTTMN',
        ];
        ksort($params);
        $params['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($params, '', '&', PHP_QUERY_RFC1738), 'TESTSECRET');

        $this->getJson(route('payment.vnpay.ipn', $params))->assertJson(['RspCode' => '00']);
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame((int) $payment->amount, (int) $appointment->fresh()->prepaid_amount);

        // Gọi lại: đã xác nhận, không cộng tiền lần nữa
        $this->getJson(route('payment.vnpay.ipn', $params))->assertJson(['RspCode' => '02']);
        $this->assertSame((int) $payment->amount, (int) $appointment->fresh()->prepaid_amount);

        // Sai số tiền
        $bad = $params;
        $bad['vnp_Amount'] = 1;
        unset($bad['vnp_SecureHash']);
        ksort($bad);
        $bad['vnp_SecureHash'] = hash_hmac('sha512', http_build_query($bad, '', '&', PHP_QUERY_RFC1738), 'TESTSECRET');
        $this->getJson(route('payment.vnpay.ipn', $bad))->assertJson(['RspCode' => '04']);

        // Return URL hiển thị kết quả
        $this->get(route('payment.vnpay.return', $params))->assertOk()->assertSee('Thanh toán thành công');
    }

    public function test_momo_falls_back_to_simulator_when_not_configured(): void
    {
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');

        $this->post(route('account.appointments.pay', $appointment), ['gateway' => 'momo']);
        $payment = Payment::firstOrFail();
        $this->assertSame(PaymentMethod::Momo, $payment->method);

        $this->get(route('payment.simulate', $payment))->assertOk()->assertSee('Ví MoMo');
        $this->post(route('payment.simulate.process', $payment), ['action' => 'success'])->assertOk()->assertSee('MoMo');
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
    }

    public function test_momo_ipn_rejects_invalid_signature(): void
    {
        config(['clinic.momo.partner_code' => 'MOMOTEST', 'clinic.momo.access_key' => 'AK', 'clinic.momo.secret_key' => 'SK']);

        $this->postJson(route('payment.momo.ipn'), ['partnerCode' => 'MOMOTEST', 'orderId' => 'X', 'amount' => 100, 'resultCode' => 0, 'signature' => 'abc'])
            ->assertStatus(400);
    }

    public function test_momo_ipn_valid_signature_confirms_payment_once(): void
    {
        config(['clinic.momo.partner_code' => 'MOMOTEST', 'clinic.momo.access_key' => 'AK', 'clinic.momo.secret_key' => 'SK']);
        Http::fake(['*' => Http::response(['resultCode' => 0, 'payUrl' => 'https://test-payment.momo.vn/pay/abc'])]);
        $appointment = $this->bookForDemo();
        $this->loginAs('benhnhan@phongkham.test');

        $res = $this->post(route('account.appointments.pay', $appointment), ['gateway' => 'momo']);
        $payment = Payment::firstOrFail();
        $this->assertStringContainsString('momo.vn', $res->headers->get('Location'));
        Http::assertSent(fn ($req) => $req['orderId'] === $payment->code && $req['amount'] === (int) $payment->amount && strlen($req['signature']) === 64);

        $momo = app(MomoService::class);
        $params = [
            'partnerCode' => 'MOMOTEST', 'orderId' => $payment->code, 'requestId' => $payment->code.'-r', 'amount' => (int) $payment->amount,
            'orderInfo' => 'test', 'orderType' => 'momo_wallet', 'transId' => 4000001, 'resultCode' => 0, 'message' => 'Thành công.',
            'payType' => 'qr', 'responseTime' => 1700000000000, 'extraData' => '',
        ];
        $params['signature'] = $momo->sign($params + ['accessKey' => 'AK'], $momo->resultFields());

        $this->postJson(route('payment.momo.ipn'), $params)->assertNoContent();
        $this->assertSame(PaymentStatus::Success, $payment->fresh()->status);
        $this->assertSame((int) $payment->amount, (int) $appointment->fresh()->prepaid_amount);

        // Gọi lại: không cộng tiền lần nữa
        $this->postJson(route('payment.momo.ipn'), $params)->assertNoContent();
        $this->assertSame((int) $payment->amount, (int) $appointment->fresh()->prepaid_amount);

        // Sai số tiền
        $bad = $params;
        $bad['amount'] = 1;
        $bad['signature'] = $momo->sign($bad + ['accessKey' => 'AK'], $momo->resultFields());
        $this->postJson(route('payment.momo.ipn'), $bad)->assertStatus(400);

        // Redirect URL hiển thị kết quả
        $this->get(route('payment.momo.return', $params))->assertOk()->assertSee('Thanh toán thành công');
    }
}
