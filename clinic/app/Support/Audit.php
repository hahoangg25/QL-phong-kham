<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Model;

/**
 * HT-09 / BR-08: ghi nhật ký mọi lần xem hồ sơ y tế (thay đổi dữ liệu đã được LogsActivity tự ghi).
 * Dùng trong controller: Audit::viewed($visit, 'Xem bệnh án');
 */
class Audit
{
    public static function viewed(Model $subject, string $description, array $properties = []): void
    {
        activity('medical_record')
            ->performedOn($subject)
            ->causedBy(auth()->user())
            ->event('viewed')
            ->withProperties($properties + ['ip' => request()->ip()])
            ->log($description);
    }

    public static function log(string $logName, string $description, ?Model $subject = null, array $properties = []): void
    {
        $logger = activity($logName)->causedBy(auth()->user())->withProperties($properties);

        if ($subject) {
            $logger->performedOn($subject);
        }

        $logger->log($description);
    }
}
