<?php

namespace App\Console\Commands;

use App\Enums\AppointmentStatus;
use App\Models\Scheduling\Appointment;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\TimeSlot;
use App\Models\User;
use App\Services\BookingService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Symfony\Component\Process\Process;

/**
 * Minh họa BR-01 / HT-02: chạy N tiến trình PHP song song cùng đặt một slot trên MySQL thật.
 * Kết quả mong đợi: số lịch tạo thành công không vượt quá sức chứa của slot.
 *
 *   php artisan clinic:race-test --n=20
 */
class RaceTest extends Command
{
    protected $signature = 'clinic:race-test
        {--n=20 : Số tiến trình đặt đồng thời}
        {--slot= : ID slot (mặc định: slot trống gần nhất từ ngày mai)}
        {--keep : Giữ lại các lịch hẹn đã tạo (mặc định sẽ dọn dẹp)}
        {--worker : (nội bộ) chạy như tiến trình con}
        {--patient= : (nội bộ)}
        {--at= : (nội bộ) thời điểm bắt đầu đồng loạt}';

    protected $description = 'Kiểm thử tranh chấp slot: nhiều tiến trình đặt cùng lúc một khung giờ (BR-01)';

    public function handle(BookingService $booking): int
    {
        return $this->option('worker') ? $this->worker($booking) : $this->coordinator();
    }

    protected function coordinator(): int
    {
        $n = max(2, (int) $this->option('n'));
        $slot = $this->option('slot')
            ? TimeSlot::with('doctor.user')->findOrFail($this->option('slot'))
            : TimeSlot::with('doctor.user')->where('is_active', true)->where('booked_count', 0)
                ->where('starts_at', '>', now()->addDay())->orderBy('starts_at')->firstOrFail();

        // Chọn N hồ sơ chưa có lịch cùng chuyên khoa trong ngày đó (để chỉ BR-01 quyết định kết quả)
        $busy = Appointment::where('specialty_id', $slot->doctor->specialty_id)->whereDate('appointment_date', $slot->date)
            ->whereIn('status', AppointmentStatus::activeValues())->pluck('patient_id');
        $patients = Patient::whereNotIn('id', $busy)->inRandomOrder()->limit($n)->pluck('id');

        if ($patients->count() < 2) {
            $this->error('Không đủ hồ sơ bệnh nhân để thử.');

            return self::FAILURE;
        }

        $before = $slot->booked_count;
        $this->info("Slot #{$slot->id}: {$slot->doctor->display_name} - {$slot->date->format('d/m/Y')} {$slot->timeLabel()}");
        $this->info("Sức chứa {$slot->capacity}, đã đặt {$before}. Khởi chạy {$patients->count()} tiến trình đặt đồng thời...");

        $at = microtime(true) + 2.0;
        $processes = $patients->map(function ($patientId) use ($slot, $at) {
            $p = new Process([PHP_BINARY, base_path('artisan'), 'clinic:race-test', '--worker', '--slot='.$slot->id, '--patient='.$patientId, '--at='.$at]);
            $p->setTimeout(60);
            $p->start();

            return $p;
        });

        $ok = 0;
        $fail = 0;
        $created = [];
        foreach ($processes as $p) {
            $p->wait();
            $out = trim($p->getOutput());
            if (str_starts_with($out, 'OK')) {
                $ok++;
                $created[] = (int) substr($out, 3);
            } else {
                $fail++;
            }
            $this->line('  '.($out ?: trim($p->getErrorOutput())));
        }

        $slot->refresh();
        $this->newLine();
        $this->table(['Sức chứa', 'Thành công', 'Bị từ chối', 'booked_count sau', 'Kết luận'], [[
            $slot->capacity, $ok, $fail, $slot->booked_count,
            ($before + $ok) <= $slot->capacity && $slot->booked_count === $before + $ok ? 'ĐẠT - không vượt sức chứa' : 'KHÔNG ĐẠT',
        ]]);

        if (! $this->option('keep') && $created) {
            DB::transaction(function () use ($created, $slot) {
                DB::table('appointment_status_logs')->whereIn('appointment_id', $created)->delete();
                Appointment::whereIn('id', $created)->delete();
                $slot->update(['booked_count' => max(0, $slot->booked_count - count($created))]);
            });
            $this->comment('Đã dọn dẹp '.count($created).' lịch hẹn thử nghiệm.');
        }

        return self::SUCCESS;
    }

    protected function worker(BookingService $booking): int
    {
        $wait = (float) $this->option('at') - microtime(true);
        if ($wait > 0) {
            usleep((int) ($wait * 1_000_000));
        }

        try {
            $appointment = $booking->bookByStaff(
                User::where('email', 'letan@phongkham.test')->first() ?? User::firstOrFail(),
                Patient::findOrFail($this->option('patient')),
                TimeSlot::findOrFail($this->option('slot')),
                'Race test',
            );
            $this->line('OK '.$appointment->id);
        } catch (\Throwable $e) {
            $this->line('FAIL '.class_basename($e).': '.$e->getMessage());
        }

        return self::SUCCESS;
    }
}
