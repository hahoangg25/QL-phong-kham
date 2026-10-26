<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Scheduling\Appointment;
use App\Notifications\AppointmentNotification;
use Illuminate\Console\Command;

/** HT-03: gửi nhắc lịch trước 24 giờ và 2 giờ. */
class SendReminders extends Command
{
    protected $signature = 'clinic:send-reminders';

    protected $description = 'Gửi nhắc lịch khám trước 24 giờ và 2 giờ';

    public function handle(): int
    {
        $sent = 0;
        $statuses = [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value];

        // Nhắc 24h: lịch trong 2h..24h tới chưa nhắc
        $due24 = Appointment::with(['patient.user', 'doctor.user', 'specialty'])
            ->whereIn('status', $statuses)
            ->whereNull('reminded_24h_at')
            ->whereBetween('scheduled_at', [now()->addHours(2), now()->addHours(24)])
            ->get();

        foreach ($due24 as $a) {
            $a->patient?->user?->notify(new AppointmentNotification($a, 'reminder'));
            $a->update(['reminded_24h_at' => now()]);
            $sent++;
        }

        // Nhắc 2h: lịch trong 2h tới chưa nhắc
        $due2 = Appointment::with(['patient.user', 'doctor.user', 'specialty'])
            ->whereIn('status', $statuses)
            ->whereNull('reminded_2h_at')
            ->whereBetween('scheduled_at', [now(), now()->addHours(2)])
            ->get();

        foreach ($due2 as $a) {
            $a->patient?->user?->notify(new AppointmentNotification($a, 'reminder'));
            $a->update(['reminded_2h_at' => now(), 'reminded_24h_at' => $a->reminded_24h_at ?? now()]);
            $sent++;
        }

        $this->info("Đã gửi {$sent} nhắc lịch.");

        return self::SUCCESS;
    }
}
