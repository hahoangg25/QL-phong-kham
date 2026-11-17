<?php

namespace App\Models\Concerns;

use Illuminate\Support\Str;

/**
 * Tự sinh mã nghiệp vụ (BN..., LH..., HD...) khi tạo bản ghi.
 * Model khai báo hằng CODE_PREFIX; mã có dạng PREFIX + yymmdd + 4 ký tự ngẫu nhiên,
 * riêng bệnh nhân dùng 6 chữ số để dễ đọc qua điện thoại.
 */
trait HasCode
{
    public static function bootHasCode(): void
    {
        static::creating(function ($model) {
            if (empty($model->code)) {
                $model->code = static::generateUniqueCode();
            }
        });
    }

    public static function generateUniqueCode(): string
    {
        do {
            $code = static::makeCode();
        } while (static::query()->where('code', $code)->exists());

        return $code;
    }

    protected static function makeCode(): string
    {
        // Bỏ các ký tự dễ nhầm (0/O, 1/I) để đọc mã qua điện thoại
        $random = collect(str_split(Str::upper(Str::random(12))))
            ->reject(fn ($c) => in_array($c, ['0', 'O', '1', 'I', 'L'], true))
            ->take(4)
            ->implode('');

        return static::CODE_PREFIX.now()->format('ymd').str_pad($random, 4, '9');
    }
}
