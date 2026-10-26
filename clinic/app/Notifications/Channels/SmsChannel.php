<?php

namespace App\Notifications\Channels;

use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

/**
 * Kênh SMS giả lập: ghi nội dung tin nhắn vào storage/logs/sms.log.
 * Thay bằng nhà cung cấp thật (eSMS, SpeedSMS...) khi triển khai.
 */
class SmsChannel
{
    public function send(object $notifiable, Notification $notification): void
    {
        $phone = $notifiable->routeNotificationFor('sms', $notification);

        if (! $phone || ! method_exists($notification, 'toSms')) {
            return;
        }

        Log::channel('sms')->info('SMS -> '.$phone.': '.$notification->toSms($notifiable));
    }
}
