<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * QT-10: duyệt nghỉ bác sĩ - khóa slot trong thời gian nghỉ, liệt kê lịch bị ảnh hưởng,
 * đổi sang bác sĩ khác hoặc hủy và báo cho bệnh nhân.
 */
class LeaveService
{
    public function __construct(protected BookingService $booking) {}

    public function approve(LeaveRequest $leave, User $by, ?string $note = null): int
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw new BusinessRuleException('Yêu cầu này đã được xử lý.');
        }

        return DB::transaction(function () use ($leave, $by, $note) {
            $leave->update([
                'status' => LeaveStatus::Approved,
                'reviewed_by' => $by->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);

            // Ngừng nhận đặt mới vào các slot trong thời gian nghỉ
            return TimeSlot::where('doctor_id', $leave->doctor_id)
                ->whereDate('date', '>=', $leave->start_date)
                ->whereDate('date', '<=', $leave->end_date)
                ->update(['is_active' => false]);
        });
    }

    public function reject(LeaveRequest $leave, User $by, ?string $note = null): void
    {
        if ($leave->status !== LeaveStatus::Pending) {
            throw new BusinessRuleException('Yêu cầu này đã được xử lý.');
        }

        $leave->update([
            'status' => LeaveStatus::Rejected,
            'reviewed_by' => $by->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }

    /** Đổi lịch bị ảnh hưởng sang slot cùng giờ của bác sĩ khác (cùng chuyên khoa). */
    public function reassign(Appointment $appointment, TimeSlot $slot, User $by): Appointment
    {
        $slot->loadMissing('doctor');
        if ($slot->doctor->specialty_id !== $appointment->specialty_id) {
            throw new BusinessRuleException('Bác sĩ thay thế phải cùng chuyên khoa.');
        }

        // Slot gốc đã bị khóa do nghỉ nên lễ tân đặt hộ với cơ chế đổi lịch
        return $this->booking->bookByStaff($by, $appointment->patient, $slot, $appointment->symptoms, $appointment->source, $appointment);
    }

    /** Hủy lịch do bác sĩ nghỉ (không tính no-show) và thông báo bệnh nhân đặt lại. */
    public function cancelAffected(Appointment $appointment, User $by): void
    {
        $this->booking->cancel($appointment, $by, 'Bác sĩ nghỉ đột xuất, vui lòng đặt lại lịch khác. Xin lỗi vì sự bất tiện.', countNoShow: false, notify: false);
        $this->booking->notify($appointment, 'doctor_leave');
    }

    /** Gợi ý slot thay thế: cùng ngày, cùng giờ, bác sĩ khác cùng chuyên khoa còn chỗ. */
    public function alternativeSlots(Appointment $appointment)
    {
        return TimeSlot::with('doctor.user')
            ->where('doctor_id', '!=', $appointment->doctor_id)
            ->whereHas('doctor', fn ($q) => $q->where('specialty_id', $appointment->specialty_id)->where('is_active', true))
            ->where('starts_at', $appointment->scheduled_at)
            ->where('is_active', true)
            ->whereColumn('booked_count', '<', 'capacity')
            ->get();
    }
}
