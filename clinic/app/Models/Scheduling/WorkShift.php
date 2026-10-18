<?php

namespace App\Models\Scheduling;

use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class WorkShift extends Model
{
    use LogsActivity;

    public const WEEKDAYS = [
        1 => 'Thứ 2', 2 => 'Thứ 3', 3 => 'Thứ 4', 4 => 'Thứ 5', 5 => 'Thứ 6', 6 => 'Thứ 7', 0 => 'Chủ nhật',
    ];

    protected $attributes = ['is_active' => true, 'slot_minutes' => 30, 'max_patients_per_slot' => 2];

    protected $fillable = [
        'doctor_id', 'room_id', 'weekday', 'start_time', 'end_time', 'slot_minutes', 'max_patients_per_slot',
        'effective_from', 'effective_to', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('schedule')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function weekdayLabel(): string
    {
        return self::WEEKDAYS[$this->weekday] ?? '';
    }

    public function timeRange(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }
}
