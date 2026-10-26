<?php

namespace App\Notifications;

use App\Models\Scheduling\Appointment;
use App\Notifications\Channels\SmsChannel;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * BN-17 / HT-03: thông báo về lịch hẹn qua email, SMS (giả lập) và trong ứng dụng.
 * Xếp vào queue khi QUEUE_CONNECTION khác "sync".
 */
class AppointmentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public const TYPES = [
        'booked' => ['Đặt lịch thành công', 'bi-calendar-check'],
        'confirmed' => ['Lịch hẹn đã được xác nhận', 'bi-check-circle'],
        'rejected' => ['Lịch hẹn bị từ chối', 'bi-x-circle'],
        'cancelled' => ['Lịch hẹn đã hủy', 'bi-calendar-x'],
        'rescheduled' => ['Đổi lịch thành công', 'bi-arrow-repeat'],
        'reminder' => ['Nhắc lịch khám', 'bi-alarm'],
        'no_show' => ['Bạn đã vắng mặt buổi hẹn', 'bi-exclamation-triangle'],
        'doctor_leave' => ['Lịch hẹn bị hủy do bác sĩ nghỉ', 'bi-person-x'],
    ];

    public function __construct(public Appointment $appointment, public string $type, public ?string $extra = null) {}

    public function via(object $notifiable): array
    {
        $channels = ['database'];

        if ($notifiable->email) {
            $channels[] = 'mail';
        }
        if (in_array($this->type, ['booked', 'confirmed', 'reminder', 'cancelled', 'doctor_leave'], true) && $notifiable->phone) {
            $channels[] = SmsChannel::class;
        }

        return $channels;
    }

    public function title(): string
    {
        return self::TYPES[$this->type][0] ?? 'Cập nhật lịch hẹn';
    }

    public function message(): string
    {
        $a = $this->appointment;
        $base = $a->patient->full_name.' - '.$a->specialty->name.' - '.$a->doctor->display_name
            .' lúc '.$a->scheduled_at->format('H:i').' ngày '.$a->scheduled_at->format('d/m/Y');

        return match ($this->type) {
            'booked' => 'Mã lịch hẹn '.$a->code.': '.$base.'. '.($a->status->value === 'pending'
                ? 'Lịch đang chờ phòng khám xác nhận.' : 'Lịch đã được xác nhận.'),
            'confirmed' => 'Lịch hẹn '.$a->code.' đã được xác nhận: '.$base.'. Vui lòng đến trước 15 phút và xuất trình mã QR.',
            'rejected' => 'Lịch hẹn '.$a->code.' bị từ chối. Lý do: '.$a->cancel_reason,
            'cancelled' => 'Lịch hẹn '.$a->code.' ('.$base.') đã được hủy.'.($a->is_late_cancel ? ' Hủy trễ được tính 1 lần vắng mặt (BR-04).' : ''),
            'rescheduled' => 'Lịch hẹn mới '.$a->code.': '.$base.'.',
            'reminder' => 'Nhắc lịch: '.$base.'. Mã lịch hẹn '.$a->code.'.',
            'no_show' => 'Lịch hẹn '.$a->code.' ('.$base.') đã bị đánh dấu vắng mặt.'.($this->extra ? ' '.$this->extra : ''),
            'doctor_leave' => 'Rất tiếc, lịch hẹn '.$a->code.' ('.$base.') bị hủy do bác sĩ nghỉ đột xuất. Vui lòng đặt lại lịch khác.',
            default => $base,
        };
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject($this->title().' - '.config('clinic.name'))
            ->greeting('Xin chào '.$notifiable->name.',')
            ->line($this->message())
            ->action('Xem lịch hẹn', route('account.appointments.show', $this->appointment))
            ->line('Hotline hỗ trợ: '.config('clinic.hotline'))
            ->salutation(config('clinic.name'));
    }

    public function toSms(object $notifiable): string
    {
        return \Illuminate\Support\Str::ascii(config('clinic.name').': '.$this->message());
    }

    public function toArray(object $notifiable): array
    {
        return [
            'title' => $this->title(),
            'message' => $this->message(),
            'icon' => self::TYPES[$this->type][1] ?? 'bi-bell',
            'url' => route('account.appointments.show', $this->appointment, false),
            'appointment_id' => $this->appointment->id,
            'type' => $this->type,
        ];
    }
}
