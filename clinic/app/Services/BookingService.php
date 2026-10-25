<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\SlotHold;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Notifications\AppointmentNotification;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Nghiệp vụ đặt lịch: giữ slot (HT-01), chống đặt trùng (HT-02, BR-01),
 * ràng buộc thời gian (BR-02), một lịch/chuyên khoa/ngày (BR-03), hạn chế no-show (BR-05),
 * hủy (BR-04) và đổi lịch (BN-13).
 *
 * Chống tranh chấp slot: mọi thao tác chiếm chỗ đều chạy trong DB::transaction và khóa dòng
 * time_slots bằng SELECT ... FOR UPDATE. Request đến sau phải chờ request trước commit,
 * rồi mới đọc lại booked_count, nên số lịch không bao giờ vượt capacity.
 */
class BookingService
{
    public function __construct(
        protected AppointmentWorkflow $workflow,
        protected OtpService $otp,
    ) {}

    // ------------------------------------------------------------------
    // Đặt lịch online: giữ slot -> xác thực OTP -> tạo lịch hẹn
    // ------------------------------------------------------------------

    /**
     * Bước 1 (BN-08): kiểm tra quy tắc, giữ slot tạm và gửi OTP.
     */
    public function hold(User $user, Patient $patient, TimeSlot $slot, ?string $symptoms, ?Appointment $rescheduleFrom = null): SlotHold
    {
        $this->assertOwnsPatient($user, $patient);
        $this->assertCanBookOnline($user);

        $otp = OtpService::generateCode();

        $hold = DB::transaction(function () use ($user, $patient, $slot, $symptoms, $rescheduleFrom, $otp) {
            $slot = $this->lockSlot($slot->id);
            $this->assertBookingWindow($slot);

            // Khóa hồ sơ bệnh nhân để hai lần đặt song song của cùng một người không lách được BR-03
            Patient::whereKey($patient->id)->lockForUpdate()->first();

            // Người dùng quay lại chọn slot khác: bỏ chỗ đang giữ cũ của hồ sơ này
            SlotHold::where('user_id', $user->id)->where('patient_id', $patient->id)->delete();

            $this->assertNoActiveSameDay($patient, $slot->doctor->specialty_id, $slot->date, $rescheduleFrom?->id);
            $this->assertHasCapacity($slot);

            return SlotHold::create([
                'token' => (string) Str::uuid(),
                'time_slot_id' => $slot->id,
                'user_id' => $user->id,
                'patient_id' => $patient->id,
                'specialty_id' => $slot->doctor->specialty_id,
                'symptoms' => $symptoms,
                'reschedule_from_id' => $rescheduleFrom?->id,
                'otp_hash' => Hash::make($otp),
                'expires_at' => now()->addMinutes((int) setting('slot_hold_minutes')),
            ]);
        });

        $this->otp->deliver($otp, 'booking', $user);

        return $hold;
    }

    public function resendHoldOtp(SlotHold $hold): void
    {
        if ($hold->isExpired()) {
            throw new BusinessRuleException('Thời gian giữ chỗ đã hết, vui lòng chọn lại khung giờ.');
        }

        if ($hold->updated_at->gt(now()->subSeconds(30))) {
            throw new BusinessRuleException('Vui lòng đợi 30 giây trước khi gửi lại mã.');
        }

        $otp = OtpService::generateCode();
        $hold->update(['otp_hash' => Hash::make($otp), 'attempts' => 0]);
        $this->otp->deliver($otp, 'booking', $hold->user);
    }

    /**
     * Bước 2 (BN-09): xác thực OTP trong thời gian giữ slot và tạo lịch hẹn.
     */
    public function confirmHold(SlotHold $hold, string $code, User $user): Appointment
    {
        if ($hold->user_id !== $user->id) {
            abort(403);
        }

        if ($hold->isExpired()) {
            $hold->delete();
            throw new BusinessRuleException('Đã hết thời gian giữ chỗ. Khung giờ đã được giải phóng, vui lòng đặt lại.');
        }

        if (! Hash::check(trim($code), $hold->otp_hash)) {
            $hold->increment('attempts');
            if ($hold->attempts >= config('clinic.otp_max_attempts')) {
                $hold->delete();
                throw new BusinessRuleException('Nhập sai OTP quá số lần cho phép. Chỗ giữ đã bị hủy, vui lòng đặt lại.');
            }
            throw new BusinessRuleException('Mã OTP không đúng. Còn '.(config('clinic.otp_max_attempts') - $hold->attempts).' lần thử.');
        }

        $appointment = DB::transaction(function () use ($hold, $user) {
            $slot = $this->lockSlot($hold->time_slot_id);

            // Chỗ của hold này đã được tính khi giữ; chỉ cần chắc chắn chưa ai vượt quá sức chứa
            $otherHolds = $slot->activeHolds()->where('id', '!=', $hold->id)->count();
            if ($slot->booked_count + $otherHolds >= $slot->capacity) {
                throw new BusinessRuleException('Khung giờ đã hết chỗ, vui lòng chọn khung giờ khác.');
            }

            $patient = Patient::whereKey($hold->patient_id)->lockForUpdate()->firstOrFail();
            $this->assertNoActiveSameDay($patient, $hold->specialty_id, $slot->date, $hold->reschedule_from_id);

            $old = $hold->reschedule_from_id ? Appointment::lockForUpdate()->find($hold->reschedule_from_id) : null;

            $status = setting('auto_confirm_online') ? AppointmentStatus::Confirmed : AppointmentStatus::Pending;
            $appointment = $this->createAppointment($slot, $patient, $user, $status, 'online', $hold->symptoms, $old);

            $hold->delete();

            return $appointment;
        });

        $this->notify($appointment, $appointment->rescheduled_from_id ? 'rescheduled' : 'booked');

        return $appointment;
    }

    // ------------------------------------------------------------------
    // Lễ tân đặt lịch hộ (LT-05) và đổi lịch hộ (LT-06)
    // ------------------------------------------------------------------

    /**
     * Dùng chung ràng buộc BR-01, BR-03 với đặt online. Lễ tân được đặt sát giờ (chỉ cần slot chưa qua),
     * không cần OTP; lịch được xác nhận ngay.
     */
    public function bookByStaff(
        User $staff,
        Patient $patient,
        TimeSlot $slot,
        ?string $symptoms = null,
        string $source = 'phone',
        ?Appointment $rescheduleFrom = null,
    ): Appointment {
        $appointment = DB::transaction(function () use ($staff, $patient, $slot, $symptoms, $source, $rescheduleFrom) {
            $slot = $this->lockSlot($slot->id);
            $this->assertBookingWindow($slot, staff: true);

            $patient = Patient::whereKey($patient->id)->lockForUpdate()->firstOrFail();
            $this->assertNoActiveSameDay($patient, $slot->doctor->specialty_id, $slot->date, $rescheduleFrom?->id);
            $this->assertHasCapacity($slot);

            $old = $rescheduleFrom ? Appointment::lockForUpdate()->find($rescheduleFrom->id) : null;

            return $this->createAppointment($slot, $patient, $staff, AppointmentStatus::Confirmed, $source, $symptoms, $old);
        });

        $this->notify($appointment, $appointment->rescheduled_from_id ? 'rescheduled' : 'booked');

        return $appointment;
    }

    // ------------------------------------------------------------------
    // Hủy lịch (BN-12, LT-06)
    // ------------------------------------------------------------------

    /**
     * @param  bool|null  $countNoShow  null = tự áp dụng BR-04 (hủy trễ tính no-show)
     */
    public function cancel(Appointment $appointment, ?User $by, ?string $reason = null, ?bool $countNoShow = null, bool $notify = true): Appointment
    {
        if (! $appointment->status->isCancellable()) {
            throw new BusinessRuleException('Lịch hẹn ở trạng thái "'.$appointment->status->label().'" không thể hủy.');
        }

        $late = $countNoShow ?? ($appointment->status === AppointmentStatus::Confirmed && $appointment->isLateCancellationNow());

        DB::transaction(function () use ($appointment, $by, $reason, $late) {
            $slot = $this->lockSlot($appointment->time_slot_id);
            $this->workflow->transition($appointment, AppointmentStatus::Cancelled, $by, $reason ?: 'Hủy lịch hẹn');
            $appointment->update(['is_late_cancel' => $late]);
            $this->releaseSlot($slot);

            if ($late) {
                $this->addNoShow($appointment);
            }
        });

        if ($notify) {
            $this->notify($appointment, 'cancelled');
        }

        return $appointment;
    }

    /** LT-02: lễ tân xác nhận lịch đang chờ. */
    public function confirm(Appointment $appointment, User $by): Appointment
    {
        $this->workflow->transition($appointment, AppointmentStatus::Confirmed, $by, 'Lễ tân xác nhận lịch hẹn');
        $this->notify($appointment, 'confirmed');

        return $appointment;
    }

    /** LT-02: từ chối lịch kèm lý do (không tính no-show). */
    public function reject(Appointment $appointment, User $by, string $reason): Appointment
    {
        if ($appointment->status !== AppointmentStatus::Pending) {
            throw new BusinessRuleException('Chỉ từ chối được lịch đang chờ xác nhận.');
        }

        $this->cancel($appointment, $by, 'Từ chối: '.$reason, countNoShow: false, notify: false);
        $this->notify($appointment, 'rejected');

        return $appointment;
    }

    // ------------------------------------------------------------------
    // Truy vấn slot trống (BN-07)
    // ------------------------------------------------------------------

    /**
     * Danh sách slot của một bác sĩ trong ngày kèm số chỗ còn lại.
     * Hai truy vấn gộp (slot + số hold đang hiệu lực) để giữ thời gian phản hồi dưới 2 giây.
     */
    public function availableSlots(int $doctorId, CarbonInterface $date, bool $staff = false): array
    {
        $slots = TimeSlot::query()
            ->where('doctor_id', $doctorId)
            ->whereDate('date', $date)
            ->where('is_active', true)
            ->withCount(['holds as active_holds' => fn ($q) => $q->where('expires_at', '>', now())])
            ->orderBy('start_time')
            ->get();

        $minStart = $staff ? now() : now()->addHours((int) setting('booking_min_hours_before'));

        return $slots->map(fn (TimeSlot $s) => [
            'id' => $s->id,
            'time' => substr($s->start_time, 0, 5),
            'end' => substr($s->end_time, 0, 5),
            'available' => $s->availableCount($s->active_holds),
            'capacity' => $s->capacity,
            'bookable' => $s->starts_at->gte($minStart) && $s->availableCount($s->active_holds) > 0,
            'period' => (int) substr($s->start_time, 0, 2) < 12 ? 'Buổi sáng' : 'Buổi chiều',
        ])->all();
    }

    // ------------------------------------------------------------------
    // Kiểm tra quy tắc
    // ------------------------------------------------------------------

    /** BR-09: bệnh nhân chỉ đặt cho hồ sơ của mình và người thân. */
    public function assertOwnsPatient(User $user, Patient $patient): void
    {
        if ($patient->user_id !== $user->id) {
            abort(403, 'Bạn không có quyền với hồ sơ bệnh nhân này.');
        }
    }

    /** BR-05 / HT-05 */
    public function assertCanBookOnline(User $user): void
    {
        if (! $user->canBookOnline()) {
            throw new BusinessRuleException(
                'Tài khoản đã vắng mặt '.$user->no_show_count.' lần nên tạm bị hạn chế đặt lịch online (BR-05). '
                .'Vui lòng liên hệ hotline '.config('clinic.hotline').' để được hỗ trợ.'
            );
        }
    }

    /** BR-02 */
    public function assertBookingWindow(TimeSlot $slot, bool $staff = false): void
    {
        if (! $slot->is_active || ! $slot->doctor?->is_active) {
            throw new BusinessRuleException('Khung giờ này hiện không nhận đặt lịch.');
        }

        if ($slot->starts_at->isPast()) {
            throw new BusinessRuleException('Khung giờ đã qua, vui lòng chọn khung giờ khác.');
        }

        if ($staff) {
            return;
        }

        $minHours = (int) setting('booking_min_hours_before');
        if ($slot->starts_at->lt(now()->addHours($minHours))) {
            throw new BusinessRuleException("Cần đặt lịch trước giờ khám ít nhất {$minHours} giờ (BR-02).");
        }

        $maxDays = (int) setting('booking_max_days_ahead');
        if ($slot->starts_at->gt(now()->addDays($maxDays)->endOfDay())) {
            throw new BusinessRuleException("Chỉ được đặt lịch trước tối đa {$maxDays} ngày (BR-02).");
        }
    }

    /** BR-03: tối đa một lịch đang hoạt động trong cùng chuyên khoa, cùng ngày. */
    public function assertNoActiveSameDay(Patient $patient, int $specialtyId, CarbonInterface|string $date, ?int $ignoreAppointmentId = null): void
    {
        $date = $date instanceof CarbonInterface ? $date->toDateString() : $date;

        $exists = Appointment::query()
            ->where('patient_id', $patient->id)
            ->where('specialty_id', $specialtyId)
            ->whereDate('appointment_date', $date)
            ->active()
            ->when($ignoreAppointmentId, fn ($q) => $q->where('id', '!=', $ignoreAppointmentId))
            ->exists();

        if ($exists) {
            throw new BusinessRuleException(
                "Hồ sơ {$patient->full_name} đã có lịch hẹn chuyên khoa này trong ngày ".
                \Carbon\Carbon::parse($date)->format('d/m/Y').' (BR-03).'
            );
        }
    }

    /** BR-01: phải gọi bên trong transaction sau khi đã khóa dòng slot. */
    protected function assertHasCapacity(TimeSlot $slot): void
    {
        if ($slot->availableCount() <= 0) {
            throw new BusinessRuleException('Khung giờ '.$slot->timeLabel().' vừa hết chỗ, vui lòng chọn khung giờ khác.');
        }
    }

    // ------------------------------------------------------------------
    // Nội bộ
    // ------------------------------------------------------------------

    /** SELECT ... FOR UPDATE trên dòng slot (HT-02). */
    protected function lockSlot(int $slotId): TimeSlot
    {
        return TimeSlot::with('doctor')->whereKey($slotId)->lockForUpdate()->firstOrFail();
    }

    protected function createAppointment(
        TimeSlot $slot,
        Patient $patient,
        User $by,
        AppointmentStatus $status,
        string $source,
        ?string $symptoms,
        ?Appointment $rescheduleFrom = null,
    ): Appointment {
        $deposit = $patient->user ? $patient->user->requiredDeposit() : 0;

        $appointment = Appointment::create([
            'patient_id' => $patient->id,
            'booked_by' => $by->id,
            'doctor_id' => $slot->doctor_id,
            'specialty_id' => $slot->doctor->specialty_id,
            'time_slot_id' => $slot->id,
            'appointment_date' => $slot->date,
            'scheduled_at' => $slot->starts_at,
            'status' => $status,
            'source' => $source,
            'symptoms' => $symptoms ?? $rescheduleFrom?->symptoms,
            'deposit_required' => $source === 'online' ? $deposit : 0,
            'prepaid_amount' => $rescheduleFrom?->prepaid_amount ?? 0,
            'confirmed_at' => $status === AppointmentStatus::Confirmed ? now() : null,
            'confirmed_by' => $status === AppointmentStatus::Confirmed && $source !== 'online' ? $by->id : null,
            'rescheduled_from_id' => $rescheduleFrom?->id,
        ]);

        $slot->increment('booked_count');
        $this->workflow->log($appointment, null, $status, $by, $rescheduleFrom
            ? 'Tạo lịch mới khi đổi từ '.$rescheduleFrom->code
            : 'Tạo lịch hẹn ('.(Appointment::SOURCES[$source] ?? $source).')');

        if ($rescheduleFrom) {
            // Lịch cũ chuyển "Đã đổi lịch", giải phóng slot cũ, tiền đã trả chuyển sang lịch mới.
            // Theo sơ đồ vòng đời, lịch còn "Chờ xác nhận" chỉ có thể kết thúc bằng "Đã hủy".
            $this->workflow->transition(
                $rescheduleFrom,
                $rescheduleFrom->status === AppointmentStatus::Pending ? AppointmentStatus::Cancelled : AppointmentStatus::Rescheduled,
                $by,
                'Đổi sang lịch '.$appointment->code,
            );
            $rescheduleFrom->update(['rescheduled_to_id' => $appointment->id, 'prepaid_amount' => 0]);
            \App\Models\Billing\Payment::where('appointment_id', $rescheduleFrom->id)->update(['appointment_id' => $appointment->id]);
            $this->releaseSlot($this->lockSlot($rescheduleFrom->time_slot_id));
        }

        return $appointment;
    }

    protected function releaseSlot(TimeSlot $slot): void
    {
        if ($slot->booked_count > 0) {
            $slot->decrement('booked_count');
        }
    }

    /** BR-04, BR-05: cộng điểm no-show cho tài khoản quản lý hồ sơ. */
    public function addNoShow(Appointment $appointment): void
    {
        $user = $appointment->patient?->user;
        $user?->increment('no_show_count');
    }

    public function notify(Appointment $appointment, string $type): void
    {
        $user = $appointment->patient?->user;

        if ($user) {
            rescue(fn () => $user->notify(new AppointmentNotification($appointment->fresh(['doctor.user', 'specialty', 'patient']), $type)));
        }
    }
}
