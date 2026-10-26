<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Scheduling\Appointment;
use App\Notifications\AppointmentNotification;
use App\Services\AppointmentWorkflow;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * HT-04: lịch đã xác nhận quá giờ mà chưa check-in -> Vắng mặt, cộng điểm no-show (BR-05).
 * Lịch còn "Chờ xác nhận" khi đã qua giờ khám -> tự hủy.
 */
class MarkNoShows extends Command
{
    protected $signature = 'clinic:mark-no-shows';

    protected $description = 'Đánh dấu vắng mặt các lịch hẹn quá giờ chưa check-in';

    public function handle(AppointmentWorkflow $workflow, BookingService $booking): int
    {
        $deadline = now()->subMinutes((int) setting('no_show_after_minutes'));
        $threshold = (int) setting('no_show_threshold');

        $noShows = Appointment::with(['patient.user', 'doctor.user', 'specialty'])
            ->where('status', AppointmentStatus::Confirmed->value)
            ->where('scheduled_at', '<', $deadline)
            ->get();

        foreach ($noShows as $a) {
            DB::transaction(function () use ($a, $workflow, $booking) {
                $workflow->transition($a, AppointmentStatus::NoShow, null, 'Hệ thống: quá giờ chưa check-in');
                $booking->addNoShow($a);
            });

            $user = $a->patient?->user?->fresh();
            if ($user) {
                $extra = $user->no_show_count >= $threshold
                    ? 'Tài khoản đã vắng mặt '.$user->no_show_count.' lần nên bị hạn chế đặt lịch online theo quy định (BR-05).'
                    : 'Số lần vắng mặt: '.$user->no_show_count.'/'.$threshold.'.';
                rescue(fn () => $user->notify(new AppointmentNotification($a, 'no_show', $extra)));
            }
        }

        $expired = Appointment::where('status', AppointmentStatus::Pending->value)
            ->where('scheduled_at', '<', now())
            ->get();

        foreach ($expired as $a) {
            $booking->cancel($a, null, 'Hệ thống: quá giờ khám nhưng chưa được xác nhận', countNoShow: false, notify: false);
        }

        $this->info('Vắng mặt: '.$noShows->count().' | Tự hủy chờ xác nhận: '.$expired->count());

        return self::SUCCESS;
    }
}
