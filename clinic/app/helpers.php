<?php

use App\Support\Settings;

if (! function_exists('setting')) {
    /** Đọc tham số quy tắc nghiệp vụ (có thể bị quản trị viên ghi đè). */
    function setting(string $key, mixed $default = null): mixed
    {
        return app(Settings::class)->get($key, $default);
    }
}

if (! function_exists('money')) {
    /** Định dạng tiền Việt: 150.000 đ */
    function money(int|float|string|null $amount): string
    {
        return number_format((float) $amount, 0, ',', '.').' đ';
    }
}

if (! function_exists('status_badge')) {
    /** Badge Bootstrap cho các enum có label() và color(). */
    function status_badge(?object $status): string
    {
        if (! $status) {
            return '';
        }

        return '<span class="badge badge-status bg-'.e($status->color()).'">'.e($status->label()).'</span>';
    }
}

if (! function_exists('mask_name')) {
    /** Ẩn bớt họ tên khi hiển thị nơi công cộng (màn hình sảnh): Nguyễn Văn An -> N*** V*** An */
    function mask_name(?string $name): string
    {
        $parts = preg_split('/\s+/u', trim((string) $name));
        $last = array_pop($parts);

        return trim(implode(' ', array_map(fn ($p) => mb_substr($p, 0, 1).'.', $parts)).' '.$last);
    }
}
