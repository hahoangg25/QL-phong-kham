<?php

namespace App\Enums;

enum PrescriptionStatus: string
{
    case Pending = 'pending';
    case Dispensed = 'dispensed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ cấp thuốc',
            self::Dispensed => 'Đã cấp thuốc',
            self::Cancelled => 'Đã hủy',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Dispensed => 'success',
            self::Cancelled => 'secondary',
        };
    }
}
