<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Waiting = 'waiting';
    case InProgress = 'in_progress';
    case AwaitingResults = 'awaiting_results';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Waiting => 'Chờ khám',
            self::InProgress => 'Đang khám',
            self::AwaitingResults => 'Chờ kết quả CLS',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Waiting => 'warning',
            self::InProgress => 'primary',
            self::AwaitingResults => 'purple',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
        };
    }

    /** Trạng thái lịch hẹn tương ứng để đồng bộ khi lượt khám đổi trạng thái. */
    public function appointmentStatus(): ?AppointmentStatus
    {
        return match ($this) {
            self::Waiting => AppointmentStatus::CheckedIn,
            self::InProgress => AppointmentStatus::InProgress,
            self::AwaitingResults => AppointmentStatus::AwaitingResults,
            self::Completed => AppointmentStatus::Completed,
            self::Cancelled => null,
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
