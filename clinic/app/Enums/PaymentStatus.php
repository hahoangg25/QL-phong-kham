<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Success = 'success';
    case Failed = 'failed';
    case Refunded = 'refunded';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Chờ thanh toán',
            self::Success => 'Thành công',
            self::Failed => 'Thất bại',
            self::Refunded => 'Đã hoàn',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Success => 'success',
            self::Failed => 'danger',
            self::Refunded => 'secondary',
        };
    }
}
