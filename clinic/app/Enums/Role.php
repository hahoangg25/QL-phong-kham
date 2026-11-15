<?php

namespace App\Enums;

/** Vai trò hệ thống (spatie/laravel-permission). */
enum Role: string
{
    case Admin = 'admin';
    case Receptionist = 'receptionist';
    case Doctor = 'doctor';
    case Patient = 'patient';

    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Quản trị viên',
            self::Receptionist => 'Lễ tân kiêm Thu ngân',
            self::Doctor => 'Bác sĩ',
            self::Patient => 'Bệnh nhân',
        };
    }

    public static function staffValues(): array
    {
        return array_values(array_map(
            fn (self $r) => $r->value,
            array_filter(self::cases(), fn (self $r) => $r !== self::Patient)
        ));
    }

    public static function staffOptions(): array
    {
        return collect(self::cases())
            ->reject(fn (self $r) => $r === self::Patient)
            ->mapWithKeys(fn (self $r) => [$r->value => $r->label()])
            ->all();
    }

    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
