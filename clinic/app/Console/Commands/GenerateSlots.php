<?php

namespace App\Console\Commands;

use App\Services\SlotGenerator;
use Illuminate\Console\Command;

/** QT-09: sinh slot tự động theo lịch tuần cho N ngày tới. */
class GenerateSlots extends Command
{
    protected $signature = 'clinic:generate-slots {--days= : Số ngày (mặc định theo cấu hình)} {--doctor= : Chỉ sinh cho một bác sĩ}';

    protected $description = 'Sinh khung giờ khám từ cấu hình ca làm việc';

    public function handle(SlotGenerator $generator): int
    {
        $count = $generator->generate(
            today(),
            $this->option('days') ? (int) $this->option('days') : null,
            $this->option('doctor') ? (int) $this->option('doctor') : null,
        );

        $this->info("Đã sinh {$count} khung giờ mới.");

        return self::SUCCESS;
    }
}
