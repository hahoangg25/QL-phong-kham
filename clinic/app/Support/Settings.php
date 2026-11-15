<?php

namespace App\Support;

use App\Models\Setting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/**
 * Tham số quy tắc nghiệp vụ (QT-11): mặc định ở config/clinic.php,
 * giá trị quản trị viên ghi đè lưu ở bảng settings.
 */
class Settings
{
    protected const CACHE_KEY = 'clinic.settings';

    protected ?array $overrides = null;

    /** Nhãn và kiểu dữ liệu hiển thị ở màn hình cấu hình. */
    public const DEFINITIONS = [
        'booking_min_hours_before' => ['BR-02', 'Đặt trước giờ khám tối thiểu (giờ)', 'int'],
        'booking_max_days_ahead' => ['BR-02', 'Đặt trước tối đa (ngày)', 'int'],
        'slot_hold_minutes' => ['HT-01', 'Thời gian giữ slot chờ OTP (phút)', 'int'],
        'auto_confirm_online' => ['LT-02', 'Tự động xác nhận lịch đặt online', 'bool'],
        'cancel_free_hours_before' => ['BR-04', 'Hủy miễn phí nếu trước giờ khám (giờ)', 'int'],
        'no_show_threshold' => ['BR-05', 'Ngưỡng số lần vắng mặt (no-show)', 'int'],
        'no_show_policy' => ['BR-05', 'Khi vượt ngưỡng', 'select:deposit=Bắt buộc đặt cọc,block=Khóa đặt lịch online'],
        'deposit_amount' => ['BR-05', 'Số tiền đặt cọc bắt buộc (đ)', 'int'],
        'no_show_after_minutes' => ['HT-04', 'Đánh dấu vắng mặt sau giờ hẹn (phút)', 'int'],
        'late_checkin_minutes' => ['BR-06', 'Check-in trễ quá (phút) thì xuống cuối hàng', 'int'],
        'priority_age_elderly' => ['LT-08', 'Ưu tiên người cao tuổi từ (tuổi)', 'int'],
        'priority_age_child' => ['LT-08', 'Ưu tiên trẻ em dưới (tuổi)', 'int'],
        'slot_generate_days' => ['QT-09', 'Sinh slot trước (ngày)', 'int'],
        'expiry_warning_days' => ['TD-10', 'Cảnh báo lô thuốc sắp hết hạn trong (ngày)', 'int'],
    ];

    public function get(string $key, mixed $default = null): mixed
    {
        $overrides = $this->overrides();

        if (array_key_exists($key, $overrides)) {
            return $this->cast($key, $overrides[$key]);
        }

        return config("clinic.rules.{$key}", $default);
    }

    public function all(): array
    {
        return collect(array_keys(self::DEFINITIONS))
            ->mapWithKeys(fn ($k) => [$k => $this->get($k)])
            ->all();
    }

    public function set(array $values): void
    {
        foreach ($values as $key => $value) {
            if (! array_key_exists($key, self::DEFINITIONS)) {
                continue;
            }
            Setting::updateOrCreate(['key' => $key], ['value' => is_bool($value) ? (int) $value : $value]);
        }

        Cache::forget(self::CACHE_KEY);
        $this->overrides = null;
    }

    protected function overrides(): array
    {
        if ($this->overrides !== null) {
            return $this->overrides;
        }

        try {
            return $this->overrides = Cache::rememberForever(
                self::CACHE_KEY,
                fn () => Schema::hasTable('settings') ? Setting::pluck('value', 'key')->all() : []
            );
        } catch (\Throwable) {
            // Chưa có CSDL (lúc cài đặt) thì dùng mặc định
            return $this->overrides = [];
        }
    }

    protected function cast(string $key, mixed $value): mixed
    {
        $type = self::DEFINITIONS[$key][2] ?? 'string';

        return match (true) {
            $type === 'int' => (int) $value,
            $type === 'bool' => (bool) $value,
            default => $value,
        };
    }
}
