<?php

namespace App\Enums;

enum ServiceOrderStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thực hiện',
            self::InProgress => 'Đang thực hiện',
            self::Completed => 'Có kết quả',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::InProgress => 'primary',
            self::Completed => 'success',
            self::Cancelled => 'secondary',
        };
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
