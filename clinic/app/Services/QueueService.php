<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Enums\VisitStatus;
use App\Events\QueueUpdated;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\User;
use App\Models\Clinical\Visit;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Tiếp đón và hàng đợi: check-in (LT-03), vãng lai (LT-04), cấp số (LT-07),
 * điều phối (LT-08), gọi bệnh nhân (BS-03), cập nhật realtime (HT-06).
 *
 * Thứ tự hàng đợi = ưu tiên (người già/trẻ em) trước, sau đó theo queue_sort:
 * - Có lịch và đến đúng giờ/sớm: queue_sort = giờ hẹn (giữ đúng vị trí đã đặt).
 * - Vãng lai: queue_sort = giờ check-in.
 * - Có lịch nhưng trễ quá K phút (BR-06): queue_sort = giờ check-in, tức xuống cuối hàng.
 */
class QueueService
{
    public function __construct(protected AppointmentWorkflow $workflow) {}

    /** LT-03: check-in bằng mã lịch hẹn/QR. */
    public function checkIn(Appointment $appointment, User $staff): Visit
    {
        if (! $appointment->appointment_date->isToday()) {
            throw new BusinessRuleException('Lịch hẹn '.$appointment->code.' là ngày '.$appointment->appointment_date->format('d/m/Y').', không phải hôm nay.');
        }

        if (! in_array($appointment->status, [AppointmentStatus::Pending, AppointmentStatus::Confirmed], true)) {
            throw new BusinessRuleException('Lịch hẹn đang ở trạng thái "'.$appointment->status->label().'", không thể check-in.');
        }

        $visit = DB::transaction(function () use ($appointment, $staff) {
            if ($appointment->status === AppointmentStatus::Pending) {
                $this->workflow->transition($appointment, AppointmentStatus::Confirmed, $staff, 'Xác nhận khi bệnh nhân đến check-in');
            }

            $late = now()->gt($appointment->scheduled_at->copy()->addMinutes((int) setting('late_checkin_minutes')));
            $patient = $appointment->patient;

            $visit = $this->createVisit(
                patient: $patient,
                doctor: $appointment->doctor,
                staff: $staff,
                queueSort: $late ? now() : $appointment->scheduled_at,
                type: 'appointment',
                appointment: $appointment,
                reason: $appointment->symptoms,
                roomId: $appointment->timeSlot?->room_id,
            );

            $this->workflow->transition($appointment, AppointmentStatus::CheckedIn, $staff, $late
                ? 'Check-in trễ quá '.setting('late_checkin_minutes').' phút, chuyển xuống cuối hàng đợi (BR-06)'
                : 'Check-in, số thứ tự '.$visit->queue_number);

            return $visit;
        });

        $this->broadcast($visit->doctor_id, 'checked_in');

        return $visit;
    }

    /** LT-04: tiếp nhận bệnh nhân vãng lai, không cần lịch hẹn. */
    public function walkIn(Patient $patient, Doctor $doctor, User $staff, ?string $reason = null, ?bool $priority = null): Visit
    {
        $exists = Visit::where('patient_id', $patient->id)->where('doctor_id', $doctor->id)->today()
            ->whereIn('status', [VisitStatus::Waiting->value, VisitStatus::InProgress->value])->exists();
        if ($exists) {
            throw new BusinessRuleException($patient->full_name.' đang có trong hàng đợi của bác sĩ này.');
        }

        $visit = DB::transaction(fn () => $this->createVisit(
            patient: $patient,
            doctor: $doctor,
            staff: $staff,
            queueSort: now(),
            type: 'walk_in',
            reason: $reason,
            priority: $priority,
        ));

        $this->broadcast($doctor->id, 'walk_in');

        return $visit;
    }

    /** BS-03: gọi bệnh nhân tiếp theo trong hàng đợi. */
    public function callNext(Doctor $doctor, User $by): ?Visit
    {
        $next = Visit::where('doctor_id', $doctor->id)->today()
            ->where('status', VisitStatus::Waiting->value)
            ->queueOrder()
            ->first();

        return $next ? $this->call($next, $by) : null;
    }

    /** Gọi một bệnh nhân cụ thể vào khám: lượt khám và lịch hẹn chuyển "Đang khám". */
    public function call(Visit $visit, User $by): Visit
    {
        if ($visit->status !== VisitStatus::Waiting) {
            throw new BusinessRuleException('Bệnh nhân không còn ở trạng thái chờ khám.');
        }

        DB::transaction(function () use ($visit, $by) {
            $visit->update([
                'status' => VisitStatus::InProgress,
                'called_at' => now(),
                'started_at' => $visit->started_at ?? now(),
            ]);
            $this->syncAppointment($visit, $by, 'Bác sĩ gọi vào khám');
        });

        $this->broadcast($visit->doctor_id, 'called', $this->announcement($visit));

        return $visit;
    }

    /** Gọi lại (thông báo lại trên màn hình sảnh). */
    public function recall(Visit $visit): void
    {
        $this->broadcast($visit->doctor_id, 'called', $this->announcement($visit));
    }

    /** Bệnh nhân không có mặt khi gọi: trả về hàng chờ, xếp cuối. */
    public function skip(Visit $visit, User $by): Visit
    {
        if ($visit->status !== VisitStatus::InProgress || $visit->clinical_notes || $visit->diagnoses()->exists()) {
            throw new BusinessRuleException('Chỉ bỏ qua được bệnh nhân vừa gọi và chưa ghi bệnh án.');
        }

        DB::transaction(function () use ($visit, $by) {
            $visit->update(['status' => VisitStatus::Waiting, 'queue_sort' => now(), 'called_at' => null]);
            if ($visit->appointment) {
                $this->workflow->transition($visit->appointment, AppointmentStatus::CheckedIn, $by, 'Không có mặt khi gọi, xếp cuối hàng', force: true);
            }
        });

        $this->broadcast($visit->doctor_id);

        return $visit;
    }

    /** LT-08: đẩy xuống cuối hàng đợi. */
    public function moveToEnd(Visit $visit): Visit
    {
        $this->assertWaiting($visit);
        $visit->update(['queue_sort' => now()]);
        $this->broadcast($visit->doctor_id);

        return $visit;
    }

    /** LT-08: bật/tắt ưu tiên (người già, trẻ em, cấp cứu nhẹ). */
    public function togglePriority(Visit $visit): Visit
    {
        $this->assertWaiting($visit);
        $visit->update(['is_priority' => ! $visit->is_priority]);
        $this->broadcast($visit->doctor_id);

        return $visit;
    }

    /** LT-08: chuyển sang bác sĩ khác (cấp số mới ở hàng đợi bác sĩ đó). */
    public function transfer(Visit $visit, Doctor $to, User $by): Visit
    {
        $this->assertWaiting($visit);
        $from = $visit->doctor_id;

        if ($from === $to->id) {
            return $visit;
        }

        DB::transaction(function () use ($visit, $to) {
            Doctor::whereKey($to->id)->lockForUpdate()->first();
            $visit->update([
                'doctor_id' => $to->id,
                'specialty_id' => $to->specialty_id,
                'room_id' => $to->room_id,
                'queue_number' => $this->nextQueueNumber($to->id),
            ]);
        });

        $this->broadcast($from);
        $this->broadcast($to->id, 'transferred');

        return $visit;
    }

    /** Hủy lượt khám đang chờ (bệnh nhân bỏ về). */
    public function cancel(Visit $visit, User $by): void
    {
        $this->assertWaiting($visit);
        $visit->update(['status' => VisitStatus::Cancelled]);
        $this->broadcast($visit->doctor_id);
    }

    // ------------------------------------------------------------------
    // Truy vấn hàng đợi
    // ------------------------------------------------------------------

    /** Hàng đợi trong ngày của một bác sĩ, nhóm theo trạng thái. */
    public function board(Doctor $doctor): array
    {
        $visits = Visit::with(['patient', 'appointment'])
            ->where('doctor_id', $doctor->id)
            ->today()
            ->queueOrder()
            ->get();

        return [
            'waiting' => $visits->where('status', VisitStatus::Waiting)->values(),
            'in_progress' => $visits->where('status', VisitStatus::InProgress)->sortByDesc('called_at')->values(),
            'awaiting_results' => $visits->where('status', VisitStatus::AwaitingResults)->values(),
            'completed' => $visits->where('status', VisitStatus::Completed)->sortByDesc('completed_at')->values(),
        ];
    }

    /** BN-18: số người còn trước bệnh nhân. */
    public function peopleAhead(Visit $visit): int
    {
        if ($visit->status !== VisitStatus::Waiting) {
            return 0;
        }

        $waiting = Visit::where('doctor_id', $visit->doctor_id)->today()
            ->where('status', VisitStatus::Waiting->value)
            ->queueOrder()
            ->pluck('id');

        return max(0, (int) $waiting->search($visit->id));
    }

    /** LT-09: dữ liệu màn hình sảnh - theo từng bác sĩ/phòng đang làm việc hôm nay. */
    public function lobby(): Collection
    {
        $visits = Visit::with(['patient:id,full_name', 'doctor.user:id,name', 'room', 'doctor.room'])
            ->today()
            ->whereIn('status', [VisitStatus::Waiting->value, VisitStatus::InProgress->value])
            ->queueOrder()
            ->get()
            ->groupBy('doctor_id');

        return $visits->map(function (Collection $items) {
            $first = $items->first();
            $current = $items->where('status', VisitStatus::InProgress)->sortByDesc('called_at')->first();

            return [
                'doctor_id' => $first->doctor_id,
                'doctor' => $first->doctor->display_name,
                'room' => ($first->room ?? $first->doctor->room)?->code ?? '-',
                'current' => $current ? ['number' => $current->queue_number, 'name' => mask_name($current->patient->full_name)] : null,
                'waiting' => $items->where('status', VisitStatus::Waiting)->take(5)->map(fn (Visit $v) => [
                    'number' => $v->queue_number,
                    'name' => mask_name($v->patient->full_name),
                    'priority' => $v->is_priority,
                ])->values(),
                'waiting_count' => $items->where('status', VisitStatus::Waiting)->count(),
            ];
        })->sortBy('room')->values();
    }

    // ------------------------------------------------------------------
    // Nội bộ
    // ------------------------------------------------------------------

    protected function createVisit(
        Patient $patient,
        Doctor $doctor,
        User $staff,
        \DateTimeInterface $queueSort,
        string $type,
        ?Appointment $appointment = null,
        ?string $reason = null,
        ?int $roomId = null,
        ?bool $priority = null,
    ): Visit {
        // Khóa dòng bác sĩ để cấp số thứ tự tuần tự, không trùng khi nhiều quầy cùng check-in
        Doctor::whereKey($doctor->id)->lockForUpdate()->first();

        return Visit::create([
            'appointment_id' => $appointment?->id,
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'specialty_id' => $doctor->specialty_id,
            'room_id' => $roomId ?? $doctor->room_id,
            'visit_date' => today(),
            'queue_number' => $this->nextQueueNumber($doctor->id),
            'is_priority' => $priority ?? $patient->isPriority(),
            'queue_sort' => $queueSort,
            'status' => VisitStatus::Waiting,
            'type' => $type,
            'checked_in_by' => $staff->id,
            'checked_in_at' => now(),
            'reason' => $reason,
        ]);
    }

    protected function nextQueueNumber(int $doctorId): int
    {
        return (int) Visit::where('doctor_id', $doctorId)->whereDate('visit_date', today())->max('queue_number') + 1;
    }

    protected function assertWaiting(Visit $visit): void
    {
        if ($visit->status !== VisitStatus::Waiting) {
            throw new BusinessRuleException('Chỉ thao tác được với bệnh nhân đang chờ khám.');
        }
    }

    /** Đồng bộ trạng thái lịch hẹn theo trạng thái lượt khám. */
    public function syncAppointment(Visit $visit, ?User $by, ?string $note = null): void
    {
        $appointment = $visit->appointment;
        $target = $visit->status->appointmentStatus();

        if ($appointment && $target && $appointment->status !== $target) {
            $this->workflow->transition($appointment, $target, $by, $note);
        }
    }

    protected function announcement(Visit $visit): array
    {
        $visit->loadMissing(['room', 'doctor.room', 'patient']);

        return [
            'number' => $visit->queue_number,
            'room' => ($visit->room ?? $visit->doctor->room)?->code ?? '',
            'name' => mask_name($visit->patient->full_name),
        ];
    }

    /** Phát sự kiện realtime; nếu Reverb chưa chạy thì bỏ qua, màn hình sẽ tự cập nhật bằng polling. */
    public function broadcast(int $doctorId, string $action = 'updated', ?array $announce = null): void
    {
        rescue(fn () => event(new QueueUpdated($doctorId, $action, $announce)), report: false);
    }
}
