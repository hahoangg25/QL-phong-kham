<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Cash = 'cash';
    case Transfer = 'transfer';
    case Vnpay = 'vnpay';
    case Momo = 'momo';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Tiền mặt',
            self::Transfer => 'Chuyển khoản',
            self::Vnpay => 'VNPay (online)',
            self::Momo => 'Ví MoMo (online)',
        };
    }

    /** Tên cổng thanh toán hiển thị cho bệnh nhân. */
    public function gatewayName(): string
    {
        return match ($this) {
            self::Momo => 'MoMo',
            default => 'VNPay',
        };
    }

    public function isOnline(): bool
    {
        return in_array($this, [self::Vnpay, self::Momo], true);
    }

    /** Cổng thanh toán online bệnh nhân chọn được (BN-10). */
    public static function onlineGateways(): array
    {
        return [self::Vnpay, self::Momo];
    }

    /** Phương thức thu ngân chọn được tại quầy (TD-02). */
    public static function counterOptions(): array
    {
        return [
            self::Cash->value => self::Cash->label(),
            self::Transfer->value => self::Transfer->label(),
        ];
    }

    public static function options(): array
    {
        return collect(self::cases())->mapWithKeys(fn (self $s) => [$s->value => $s->label()])->all();
    }
}
