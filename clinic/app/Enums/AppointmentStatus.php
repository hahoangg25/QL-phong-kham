<?php

namespace App\Enums;

/**
 * Vòng đời lịch hẹn - 9 trạng thái.
 * Kết thúc ở: Hoàn thành, Đã hủy, Vắng mặt hoặc Đã đổi lịch.
 */
enum AppointmentStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case CheckedIn = 'checked_in';
    case InProgress = 'in_progress';
    case AwaitingResults = 'awaiting_results';
    case Completed = 'completed';
    case Cancelled = 'cancelled';
    case Rescheduled = 'rescheduled';
    case NoShow = 'no_show';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ xác nhận',
            self::Confirmed => 'Đã xác nhận',
            self::CheckedIn => 'Đã check-in',
            self::InProgress => 'Đang khám',
            self::AwaitingResults => 'Chờ kết quả CLS',
            self::Completed => 'Hoàn thành',
            self::Cancelled => 'Đã hủy',
            self::Rescheduled => 'Đã đổi lịch',
            self::NoShow => 'Vắng mặt',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Confirmed => 'primary',
            self::CheckedIn => 'info',
            self::InProgress, self::AwaitingResults => 'purple',
            self::Completed => 'success',
            self::Cancelled, self::NoShow => 'danger',
            self::Rescheduled => 'secondary',
        };
    }

    /** Các trạng thái được phép chuyển tới (theo sơ đồ vòng đời). */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Pending => [self::Confirmed, self::Cancelled],
            self::Confirmed => [self::CheckedIn, self::Cancelled, self::Rescheduled, self::NoShow],
            self::CheckedIn => [self::InProgress],
            self::InProgress => [self::AwaitingResults, self::Completed],
            self::AwaitingResults => [self::InProgress, self::Completed],
            default => [],
        };
    }

    public function canTransitionTo(self $to): bool
    {
        return in_array($to, $this->allowedTransitions(), true);
    }

    /** Lịch đang chiếm slot / đang hoạt động (BR-01, BR-03). */
    public static function active(): array
    {
        return [self::Pending, self::Confirmed, self::CheckedIn, self::InProgress, self::AwaitingResults];
    }

    public static function activeValues(): array
    {
        return array_map(fn (self $s) => $s->value, self::active());
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::Rescheduled, self::NoShow], true);
    }

    /** Còn được hủy/đổi lịch. */
    public function isCancellable(): bool
    {
        return in_array($this, [self::Pending, self::Confirmed], true);
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
