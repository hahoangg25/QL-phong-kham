<?php

namespace App\Console\Commands;

use App\Models\Scheduling\SlotHold;
use Illuminate\Console\Command;

/**
 * HT-01: dọn các chỗ giữ tạm đã hết hạn. (Chỗ hết hạn vốn đã không được tính khi
 * kiểm tra sức chứa; lệnh này chỉ dọn dữ liệu.)
 */
class ReleaseExpiredHolds extends Command
{
    protected $signature = 'clinic:release-holds';

    protected $description = 'Giải phóng các slot giữ tạm đã hết hạn';

    public function handle(): int
    {
        $count = SlotHold::where('expires_at', '<=', now())->delete();
        $this->info("Đã giải phóng {$count} chỗ giữ tạm.");

        return self::SUCCESS;
    }
}
