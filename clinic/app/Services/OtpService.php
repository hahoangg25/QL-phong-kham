<?php

namespace App\Services;

use App\Exceptions\BusinessRuleException;
use App\Models\OtpCode;
use App\Notifications\OtpCodeNotification;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

/** Sinh và xác thực mã OTP (BN-01, BN-02, BN-09). OTP gửi qua email, SMS giả lập. */
class OtpService
{
    public const PURPOSES = [
        'register' => 'xác thực tài khoản',
        'reset_password' => 'đặt lại mật khẩu',
        'booking' => 'xác nhận đặt lịch khám',
    ];

    public static function generateCode(): string
    {
        return (string) random_int(100000, 999999);
    }

    /**
     * @param  object|null  $notifiable  User nhận OTP; null thì gửi thẳng tới email trong $identifier
     */
    public function send(string $identifier, string $purpose, ?object $notifiable = null): string
    {
        $last = OtpCode::where('identifier', $identifier)->where('purpose', $purpose)->latest('id')->first();
        if ($last && $last->created_at->gt(now()->subSeconds(30))) {
            throw new BusinessRuleException('Vui lòng đợi 30 giây trước khi yêu cầu mã OTP mới.');
        }

        OtpCode::where('identifier', $identifier)->where('purpose', $purpose)->whereNull('consumed_at')->delete();

        $code = self::generateCode();
        OtpCode::create([
            'identifier' => $identifier,
            'purpose' => $purpose,
            'code_hash' => Hash::make($code),
            'expires_at' => now()->addMinutes(config('clinic.otp_ttl_minutes')),
        ]);

        $this->deliver($code, $purpose, $notifiable ?? Notification::route('mail', $identifier));

        return $code;
    }

    public function deliver(string $code, string $purpose, object $notifiable): void
    {
        $notifiable->notify(new OtpCodeNotification($code, self::PURPOSES[$purpose] ?? $purpose));

        // Chế độ demo: hiển thị OTP trên màn hình vì email chạy driver "log"
        if (config('clinic.show_otp')) {
            session()->flash('dev_otp', $code);
        }
    }

    public function verify(string $identifier, string $purpose, string $code): bool
    {
        $otp = OtpCode::where('identifier', $identifier)->where('purpose', $purpose)
            ->whereNull('consumed_at')->latest('id')->first();

        if (! $otp || ! $otp->isUsable()) {
            throw new BusinessRuleException('Mã OTP đã hết hạn hoặc không tồn tại. Vui lòng gửi lại mã mới.');
        }

        if (! Hash::check(trim($code), $otp->code_hash)) {
            $otp->increment('attempts');
            $left = config('clinic.otp_max_attempts') - $otp->attempts;

            throw new BusinessRuleException($left > 0
                ? "Mã OTP không đúng. Bạn còn {$left} lần thử."
                : 'Bạn đã nhập sai quá số lần cho phép. Vui lòng gửi lại mã mới.');
        }

        $otp->update(['consumed_at' => now()]);

        return true;
    }
}
