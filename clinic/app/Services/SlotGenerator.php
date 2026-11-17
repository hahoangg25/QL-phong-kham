<?php

namespace App\Services;

use App\Enums\LeaveStatus;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Scheduling\TimeSlot;
use App\Models\Scheduling\WorkShift;
use Carbon\Carbon;
use Carbon\CarbonInterface;

/**
 * QT-09: sinh khung giờ (slot) tự động từ cấu hình ca làm việc tuần cho N ngày tới.
 * Bỏ qua ngày bác sĩ nghỉ đã được duyệt; slot đã tồn tại được giữ nguyên (insert ignore theo unique).
 */
class SlotGenerator
{
    public function generate(?CarbonInterface $from = null, ?int $days = null, ?int $doctorId = null): int
    {
        $from = Carbon::parse($from ?? today())->startOfDay();
        $days ??= (int) setting('slot_generate_days');
        $to = $from->copy()->addDays($days - 1);

        $shifts = WorkShift::with('doctor')
            ->where('is_active', true)
            ->when($doctorId, fn ($q) => $q->where('doctor_id', $doctorId))
            ->get()
            ->groupBy('weekday');

        $leaves = LeaveRequest::where('status', LeaveStatus::Approved->value)
            ->whereDate('start_date', '<=', $to)
            ->whereDate('end_date', '>=', $from)
            ->get()
            ->groupBy('doctor_id');

        $rows = [];
        $now = now();

        for ($date = $from->copy(); $date->lte($to); $date->addDay()) {
            foreach ($shifts->get($date->dayOfWeek, []) as $shift) {
                if (! $shift->doctor?->is_active) {
                    continue;
                }
                if ($shift->effective_from && $date->lt($shift->effective_from)) {
                    continue;
                }
                if ($shift->effective_to && $date->gt($shift->effective_to)) {
                    continue;
                }
                $onLeave = $leaves->get($shift->doctor_id, collect())
                    ->contains(fn ($l) => $date->between($l->start_date, $l->end_date));
                if ($onLeave) {
                    continue;
                }

                $cursor = Carbon::parse($date->toDateString().' '.$shift->start_time);
                $end = Carbon::parse($date->toDateString().' '.$shift->end_time);

                while ($cursor->copy()->addMinutes($shift->slot_minutes)->lte($end)) {
                    $slotEnd = $cursor->copy()->addMinutes($shift->slot_minutes);
                    $rows[] = [
                        'doctor_id' => $shift->doctor_id,
                        'room_id' => $shift->room_id ?? $shift->doctor->room_id,
                        'work_shift_id' => $shift->id,
                        'date' => $date->toDateString(),
                        'start_time' => $cursor->format('H:i:s'),
                        'end_time' => $slotEnd->format('H:i:s'),
                        'starts_at' => $cursor->format('Y-m-d H:i:s'),
                        'capacity' => $shift->max_patients_per_slot,
                        'booked_count' => 0,
                        'is_active' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ];
                    $cursor = $slotEnd;
                }
            }
        }

        $created = 0;
        foreach (array_chunk($rows, 500) as $chunk) {
            $created += TimeSlot::insertOrIgnore($chunk);
        }

        return $created;
    }
}
