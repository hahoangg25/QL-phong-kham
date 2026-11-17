<?php

namespace Tests\Feature;

use App\Enums\AppointmentStatus;
use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Enums\PrescriptionStatus;
use App\Enums\VisitStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Pharmacy\Medicine;
use App\Models\Pharmacy\MedicineBatch;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\SlotHold;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Models\Clinical\Visit;
use App\Services\BillingService;
use App\Services\BookingService;
use App\Services\ExaminationService;
use App\Services\PharmacyService;
use App\Services\QueueService;
use App\Support\Settings;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Kiểm thử các quy tắc nghiệp vụ BR-01..BR-10 và tác vụ tự động HT-01, HT-04 ở tầng service.
 */
class BusinessRulesTest extends TestCase
{
    protected BookingService $booking;

    protected User $receptionist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->booking = app(BookingService::class);
        $this->receptionist = User::where('email', 'letan@phongkham.test')->first();
    }

    /** Slot còn chỗ của ngày mai (đủ xa để qua BR-02). */
    protected function futureSlot(?int $doctorId = null): TimeSlot
    {
        return TimeSlot::with('doctor')
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->where('starts_at', '>', now()->addHours(26))
            ->orderBy('starts_at')
            ->firstOrFail();
    }

    /** Tạo tài khoản bệnh nhân mới với một hồ sơ. */
    protected function newPatientAccount(string $suffix): array
    {
        $user = User::create([
            'name' => 'BN '.$suffix, 'email' => "bn{$suffix}@test.local", 'phone' => '08'.str_pad((string) crc32($suffix) % 100000000, 8, '0', STR_PAD_LEFT),
            'password' => 'password', 'verified_at' => now(), 'consent_at' => now(),
        ]);
        $user->assignRole('patient');
        $patient = Patient::create(['user_id' => $user->id, 'full_name' => 'BN '.$suffix, 'date_of_birth' => '1990-01-01', 'gender' => 'male']);

        return [$user, $patient];
    }

    // ---------------------------------------------------------------- BR-01

    public function test_br01_slot_never_exceeds_capacity(): void
    {
        $slot = $this->futureSlot();
        $slot->update(['capacity' => 2]);

        for ($i = 1; $i <= 2; $i++) {
            [$user, $patient] = $this->newPatientAccount("cap{$i}");
            $this->booking->bookByStaff($this->receptionist, $patient, $slot->fresh());
        }

        [$user, $patient] = $this->newPatientAccount('cap3');
        $this->expectException(BusinessRuleException::class);

        try {
            $this->booking->bookByStaff($this->receptionist, $patient, $slot->fresh());
        } finally {
            $this->assertSame(2, $slot->fresh()->booked_count);
            $this->assertSame(2, Appointment::where('time_slot_id', $slot->id)->count());
        }
    }

    public function test_ht01_active_hold_reserves_capacity_and_expired_hold_releases_it(): void
    {
        $slot = $this->futureSlot();
        $slot->update(['capacity' => 1]);

        [$u1, $p1] = $this->newPatientAccount('h1');
        [$u2, $p2] = $this->newPatientAccount('h2');

        $hold = $this->booking->hold($u1, $p1, $slot->fresh(), 'Đau đầu');
        $this->assertSame(0, $slot->fresh()->availableCount());

        // Người thứ hai không giữ được chỗ khi chỗ duy nhất đang được giữ
        try {
            $this->booking->hold($u2, $p2, $slot->fresh(), null);
            $this->fail('Expected BusinessRuleException');
        } catch (BusinessRuleException) {
        }

        // Hết hạn giữ chỗ -> chỗ được giải phóng
        $hold->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(1, $slot->fresh()->availableCount());
        $this->booking->hold($u2, $p2, $slot->fresh(), null);

        Artisan::call('clinic:release-holds');
        $this->assertFalse(SlotHold::whereKey($hold->id)->exists());
    }

    public function test_bn09_confirm_hold_with_otp_creates_pending_appointment(): void
    {
        $slot = $this->futureSlot();
        [$user, $patient] = $this->newPatientAccount('otp');

        $hold = $this->booking->hold($user, $patient, $slot, 'Ho');
        $hold->update(['otp_hash' => Hash::make('123456')]);

        try {
            $this->booking->confirmHold($hold->fresh(), '000000', $user);
            $this->fail('Sai OTP phải bị từ chối');
        } catch (BusinessRuleException) {
            $this->assertSame(1, $hold->fresh()->attempts);
        }

        $appointment = $this->booking->confirmHold($hold->fresh(), '123456', $user);

        $this->assertSame(AppointmentStatus::Pending, $appointment->status);
        $this->assertSame(1, $slot->fresh()->booked_count);
        $this->assertFalse(SlotHold::whereKey($hold->id)->exists());
        $this->assertSame(1, $appointment->statusLogs()->count());
    }

    // ---------------------------------------------------------------- BR-02

    public function test_br02_booking_window_min_hours_and_max_days(): void
    {
        [$user, $patient] = $this->newPatientAccount('win');

        $tooSoon = TimeSlot::where('starts_at', '>', now())->where('starts_at', '<', now()->addHours(2))->first()
            ?? tap($this->futureSlot())->update(['starts_at' => now()->addMinutes(30)]);
        $this->expectException(BusinessRuleException::class);
        $this->booking->hold($user, $patient, $tooSoon->fresh(), null);
    }

    public function test_br02_max_days_ahead(): void
    {
        app(Settings::class)->set(['booking_max_days_ahead' => 3]);
        [$user, $patient] = $this->newPatientAccount('far');
        $far = TimeSlot::where('date', '>', today()->addDays(5))->firstOrFail();

        $this->expectExceptionMessage('tối đa 3 ngày');
        $this->booking->hold($user, $patient, $far, null);
    }

    // ---------------------------------------------------------------- BR-03

    public function test_br03_one_active_appointment_per_specialty_per_day(): void
    {
        $slot = $this->futureSlot();
        [$user, $patient] = $this->newPatientAccount('same');
        $this->booking->bookByStaff($this->receptionist, $patient, $slot);

        $other = TimeSlot::where('doctor_id', $slot->doctor_id)->whereDate('date', $slot->date)
            ->where('id', '!=', $slot->id)->firstOrFail();

        $this->expectExceptionMessage('BR-03');
        $this->booking->bookByStaff($this->receptionist, $patient, $other);
    }

    // ---------------------------------------------------------------- BR-04 / BR-05

    public function test_br04_late_cancellation_counts_as_no_show(): void
    {
        [$user, $patient] = $this->newPatientAccount('late');
        $slot = $this->futureSlot();
        $appointment = $this->booking->bookByStaff($this->receptionist, $patient, $slot);

        // Hủy sớm: miễn phí
        $this->booking->cancel($appointment, $user, 'Bận');
        $this->assertSame(0, $user->fresh()->no_show_count);
        $this->assertSame(0, $slot->fresh()->booked_count);

        // Hủy trễ (giờ khám còn 1 giờ < 4 giờ quy định)
        $soon = TimeSlot::where('id', '!=', $slot->id)->where('starts_at', '>', now()->addHours(26))->firstOrFail();
        $late = $this->booking->bookByStaff($this->receptionist, $patient, $soon);
        $late->update(['scheduled_at' => now()->addHour()]);
        $this->booking->cancel($late->fresh(), $user, 'Quên');

        $this->assertTrue($late->fresh()->is_late_cancel);
        $this->assertSame(1, $user->fresh()->no_show_count);
    }

    public function test_br05_block_policy_prevents_online_booking(): void
    {
        app(Settings::class)->set(['no_show_policy' => 'block', 'no_show_threshold' => 2]);
        [$user, $patient] = $this->newPatientAccount('block');
        $user->update(['no_show_count' => 2]);

        $this->expectExceptionMessage('BR-05');
        $this->booking->hold($user->fresh(), $patient, $this->futureSlot(), null);
    }

    public function test_br05_deposit_policy_requires_deposit(): void
    {
        app(Settings::class)->set(['no_show_policy' => 'deposit', 'no_show_threshold' => 2, 'deposit_amount' => 150000]);
        [$user, $patient] = $this->newPatientAccount('dep');
        $user->update(['no_show_count' => 3]);

        $hold = $this->booking->hold($user->fresh(), $patient, $this->futureSlot(), null);
        $hold->update(['otp_hash' => Hash::make('111111')]);
        $appointment = $this->booking->confirmHold($hold->fresh(), '111111', $user->fresh());

        $this->assertSame(150000, $appointment->deposit_required);
    }

    // ---------------------------------------------------------------- HT-04

    public function test_ht04_mark_no_shows_command(): void
    {
        [$user, $patient] = $this->newPatientAccount('ns');
        $appointment = $this->booking->bookByStaff($this->receptionist, $patient, $this->futureSlot());
        $appointment->update(['scheduled_at' => now()->subHours(3)]);

        [$user2, $patient2] = $this->newPatientAccount('ns2');
        $pending = $this->booking->bookByStaff($this->receptionist, $patient2, $this->futureSlot());
        $pending->forceFill(['status' => AppointmentStatus::Pending, 'scheduled_at' => now()->subHour()])->save();

        Artisan::call('clinic:mark-no-shows');

        $this->assertSame(AppointmentStatus::NoShow, $appointment->fresh()->status);
        $this->assertSame(1, $user->fresh()->no_show_count);
        $this->assertSame(AppointmentStatus::Cancelled, $pending->fresh()->status);
    }

    // ---------------------------------------------------------------- HT-03

    public function test_ht03_reminders_sent_once_24h_and_2h_before(): void
    {
        \Illuminate\Support\Facades\Notification::fake();
        [$user, $patient] = $this->newPatientAccount('rem');
        $a = $this->booking->bookByStaff($this->receptionist, $patient, $this->futureSlot());
        $a->update(['scheduled_at' => now()->addHours(20)]);

        Artisan::call('clinic:send-reminders');
        Artisan::call('clinic:send-reminders'); // chạy lại không gửi trùng

        $this->assertNotNull($a->fresh()->reminded_24h_at);
        $this->assertNull($a->fresh()->reminded_2h_at);

        $a->update(['scheduled_at' => now()->addMinutes(90)]);
        Artisan::call('clinic:send-reminders');
        $this->assertNotNull($a->fresh()->reminded_2h_at);

        \Illuminate\Support\Facades\Notification::assertSentToTimes($user, \App\Notifications\AppointmentNotification::class, 3);
    }

    // ---------------------------------------------------------------- Vòng đời

    public function test_state_machine_rejects_invalid_transition(): void
    {
        [$user, $patient] = $this->newPatientAccount('sm');
        $appointment = $this->booking->bookByStaff($this->receptionist, $patient, $this->futureSlot());
        $this->booking->cancel($appointment, $user, 'x');

        $this->expectException(BusinessRuleException::class);
        $this->booking->confirm($appointment->fresh(), $this->receptionist);
    }

    public function test_reschedule_marks_old_appointment_and_moves_slot(): void
    {
        [$user, $patient] = $this->newPatientAccount('rs');
        $slotA = $this->futureSlot();
        $old = $this->booking->bookByStaff($this->receptionist, $patient, $slotA);

        $slotB = TimeSlot::where('doctor_id', $slotA->doctor_id)->whereDate('date', '>', $slotA->date)->firstOrFail();
        $new = $this->booking->bookByStaff($this->receptionist, $patient, $slotB, null, 'phone', $old);

        $this->assertSame(AppointmentStatus::Rescheduled, $old->fresh()->status);
        $this->assertSame($new->id, $old->fresh()->rescheduled_to_id);
        $this->assertSame(0, $slotA->fresh()->booked_count);
        $this->assertSame(1, $slotB->fresh()->booked_count);
    }

    // ---------------------------------------------------------------- BR-06

    public function test_br06_late_checkin_goes_to_end_of_queue(): void
    {
        $queue = app(QueueService::class);
        $doctor = Doctor::first();
        $slots = TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', '>', today())->orderBy('starts_at')->take(2)->get();

        [$u1, $onTime] = $this->newPatientAccount('q1');
        [$u2, $late] = $this->newPatientAccount('q2');
        [$u3, $walk] = $this->newPatientAccount('q3');

        $a1 = $this->booking->bookByStaff($this->receptionist, $onTime, $slots[0]);
        $a2 = $this->booking->bookByStaff($this->receptionist, $late, $slots[1]);
        // Đưa hai lịch về hôm nay: a1 hẹn 30 phút nữa (đến sớm), a2 hẹn cách đây 1 giờ (trễ > 15 phút)
        $a1->update(['appointment_date' => today(), 'scheduled_at' => now()->addMinutes(30)]);
        $a2->update(['appointment_date' => today(), 'scheduled_at' => now()->subHour()]);

        $vWalk = $queue->walkIn($walk, $doctor, $this->receptionist);
        $vLate = $queue->checkIn($a2->fresh(), $this->receptionist);
        $vOnTime = $queue->checkIn($a1->fresh(), $this->receptionist);

        $order = Visit::where('doctor_id', $doctor->id)->today()->where('is_priority', false)->queueOrder()->pluck('id')->all();

        // Vãng lai đến trước -> trước; người đến trễ bị xếp sau người vãng lai; người đúng giờ theo giờ hẹn
        $this->assertSame([$vWalk->id, $vLate->id, $vOnTime->id], $order);
        $this->assertStringContainsString('BR-06', $a2->fresh()->statusLogs()->first()->note);
        $this->assertSame([1, 2, 3], [$vWalk->queue_number, $vLate->queue_number, $vOnTime->queue_number]);
    }

    // ---------------------------------------------------------------- BR-07 / BR-08 / BR-10 / FEFO

    protected function examinedVisit(): Visit
    {
        $queue = app(QueueService::class);
        $doctor = Doctor::first();
        [$u, $patient] = $this->newPatientAccount('ex');
        $visit = $queue->walkIn($patient, $doctor, $this->receptionist, 'Sốt');
        $queue->call($visit, $doctor->user);

        return $visit->fresh();
    }

    public function test_br07_only_assigned_doctor_can_write_record(): void
    {
        $visit = $this->examinedVisit();
        $otherDoctor = Doctor::where('id', '!=', $visit->doctor_id)->first()->user;

        $this->expectException(\Symfony\Component\HttpKernel\Exception\HttpException::class);
        app(ExaminationService::class)->saveClinical($visit, ['pulse' => 80], $otherDoctor);
    }

    public function test_br08_completed_visit_is_locked_and_only_addendum_allowed(): void
    {
        $visit = $this->examinedVisit();
        $doctor = $visit->doctor->user;
        $exam = app(ExaminationService::class);

        $exam->syncDiagnoses($visit, [['icd10_code' => 'J00', 'name' => 'Viêm mũi họng cấp']], $doctor);
        $exam->complete($visit, $doctor);

        $visit->refresh();
        $this->assertTrue($visit->isLocked());
        $this->assertSame(VisitStatus::Completed, $visit->status);
        $this->assertNotNull($visit->invoice);

        try {
            $exam->saveClinical($visit, ['pulse' => 90], $doctor);
            $this->fail('Bệnh án đã khóa không được sửa');
        } catch (BusinessRuleException) {
        }

        $exam->addAddendum($visit, 'Bổ sung: bệnh nhân báo đỡ sốt.', $doctor);
        $this->assertSame(1, $visit->addenda()->count());
    }

    public function test_fefo_dispense_and_br10_dispensed_prescription_is_final(): void
    {
        $visit = $this->examinedVisit();
        $doctor = $visit->doctor->user;
        $exam = app(ExaminationService::class);

        $medicine = Medicine::where('code', 'T001')->first();
        $batches = MedicineBatch::where('medicine_id', $medicine->id)->orderBy('expiry_date')->get();
        $early = $batches->first();
        $late = $batches->last();

        $exam->syncDiagnoses($visit, [['icd10_code' => 'R50.9', 'name' => 'Sốt']], $doctor);
        $qty = $early->remaining + 5; // vượt lô sớm để buộc lấy sang lô muộn
        $exam->savePrescription($visit, [['medicine_id' => $medicine->id, 'quantity' => $qty, 'dosage' => '1 viên', 'days' => 3]], null, $doctor);
        $exam->complete($visit, $doctor);

        $prescription = $visit->fresh()->prescription;
        $pharmacy = app(PharmacyService::class);
        $pharmacist = User::where('email', 'duocsi@phongkham.test')->first();

        // TD-05: chưa thanh toán thì chưa cấp
        try {
            $pharmacy->dispense($prescription, $pharmacist);
            $this->fail('Đơn chưa thanh toán không được cấp');
        } catch (BusinessRuleException) {
        }

        app(BillingService::class)->pay($visit->fresh()->invoice, PaymentMethod::Cash, User::where('email', 'thungan@phongkham.test')->first());
        $this->assertSame(InvoiceStatus::Paid, $visit->fresh()->invoice->status);

        $pharmacy->dispense($prescription->fresh(), $pharmacist);

        $this->assertSame(0, $early->fresh()->remaining, 'Lô hết hạn sớm phải được xuất hết trước (FEFO)');
        $this->assertSame($late->remaining - 5, $late->fresh()->remaining);
        $this->assertSame(PrescriptionStatus::Dispensed, $prescription->fresh()->status);
        $this->assertFalse($prescription->fresh()->isEditable());

        $this->expectException(BusinessRuleException::class);
        $pharmacy->dispense($prescription->fresh(), $pharmacist);
    }

    public function test_br10_allergy_warning_when_prescribing(): void
    {
        $visit = $this->examinedVisit();
        $visit->patient->update(['allergies' => 'Amoxicillin']);
        $doctor = $visit->doctor->user;

        $warnings = app(ExaminationService::class)->savePrescription($visit->fresh(), [
            ['medicine_id' => Medicine::where('code', 'T004')->value('id'), 'quantity' => 10, 'dosage' => '1 viên', 'days' => 5],
        ], null, $doctor);

        $this->assertNotEmpty($warnings);
        $this->assertStringContainsString('DỊ ỨNG', implode(' ', $warnings));
    }

    public function test_br11_sensitive_fields_are_encrypted_at_rest(): void
    {
        [$user, $patient] = $this->newPatientAccount('enc');
        $patient->update(['allergies' => 'Penicillin', 'medical_history' => 'Hen phế quản']);

        $raw = \DB::table('patients')->where('id', $patient->id)->first();
        $this->assertNotSame('Penicillin', $raw->allergies);
        $this->assertSame('Penicillin', $patient->fresh()->allergies);
    }
}
