<?php

namespace App\Notifications;

use App\Notifications\Channels\SmsChannel;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Gửi mã OTP ngay lập tức (không xếp hàng đợi) qua email và SMS giả lập. */
class OtpCodeNotification extends Notification
{
    public function __construct(public string $code, public string $purpose) {}

    public function via(object $notifiable): array
    {
        $channels = ['mail'];
        if (method_exists($notifiable, 'routeNotificationForSms') && $notifiable->routeNotificationForSms()) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Mã xác thực '.$this->code.' - '.config('clinic.name'))
            ->greeting('Xin chào!')
            ->line('Mã OTP để '.$this->purpose.' của bạn là:')
            ->line('**'.$this->code.'**')
            ->line('Mã có hiệu lực trong '.config('clinic.otp_ttl_minutes').' phút. Không chia sẻ mã này cho bất kỳ ai.')
            ->salutation(config('clinic.name'));
    }

    public function toSms(object $notifiable): string
    {
        return config('clinic.name').': Ma OTP '.$this->purpose.' la '.$this->code.'. Hieu luc '.config('clinic.otp_ttl_minutes').' phut.';
    }
}
