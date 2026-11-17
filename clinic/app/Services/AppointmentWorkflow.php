<?php

namespace App\Services;

use App\Enums\AppointmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\AppointmentStatusLog;
use App\Models\User;

/**
 * Máy trạng thái lịch hẹn: kiểm tra chuyển trạng thái hợp lệ theo sơ đồ vòng đời
 * và ghi lại người thực hiện, thời điểm cho mỗi lần chuyển.
 */
class AppointmentWorkflow
{
    public function transition(
        Appointment $appointment,
        AppointmentStatus $to,
        ?User $by = null,
        ?string $note = null,
        bool $force = false,
    ): Appointment {
        $from = $appointment->status;

        if ($from === $to) {
            return $appointment;
        }

        if (! $force && ! $from->canTransitionTo($to)) {
            throw new BusinessRuleException(
                "Không thể chuyển lịch hẹn {$appointment->code} từ \"{$from->label()}\" sang \"{$to->label()}\"."
            );
        }

        $appointment->status = $to;

        match ($to) {
            AppointmentStatus::Confirmed => $appointment->forceFill(['confirmed_at' => now(), 'confirmed_by' => $by?->id]),
            AppointmentStatus::CheckedIn => $appointment->forceFill(['checked_in_at' => now()]),
            AppointmentStatus::Cancelled => $appointment->forceFill([
                'cancelled_at' => now(), 'cancelled_by' => $by?->id, 'cancel_reason' => $note,
            ]),
            default => null,
        };

        $appointment->save();
        $this->log($appointment, $from, $to, $by, $note);

        return $appointment;
    }

    public function log(Appointment $appointment, ?AppointmentStatus $from, AppointmentStatus $to, ?User $by, ?string $note = null): void
    {
        AppointmentStatusLog::create([
            'appointment_id' => $appointment->id,
            'from_status' => $from?->value,
            'to_status' => $to->value,
            'user_id' => $by?->id,
            'note' => $note,
        ]);
    }
}
