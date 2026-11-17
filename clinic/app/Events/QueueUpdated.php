<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * HT-06: đẩy cập nhật hàng đợi qua WebSocket (Laravel Reverb) tới màn hình lễ tân, bác sĩ và sảnh.
 * Kênh công khai chỉ mang số thứ tự và phòng, không chứa thông tin y tế.
 */
class QueueUpdated implements ShouldBroadcastNow
{
    use Dispatchable;

    public function __construct(
        public int $doctorId,
        public string $action = 'updated',
        public ?array $announce = null,
    ) {}

    public function broadcastOn(): array
    {
        return [new Channel('queue')];
    }

    public function broadcastAs(): string
    {
        return 'queue.updated';
    }
}
