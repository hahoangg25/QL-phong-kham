<?php

namespace App\Models\Scheduling;

use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    protected $attributes = ['is_active' => true, 'booked_count' => 0];

    protected $fillable = [
        'doctor_id', 'room_id', 'work_shift_id', 'date', 'start_time', 'end_time', 'starts_at', 'capacity',
        'booked_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'starts_at' => 'datetime',
            'is_active' => 'boolean',
        ];
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function workShift(): BelongsTo
    {
        return $this->belongsTo(WorkShift::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function holds(): HasMany
    {
        return $this->hasMany(SlotHold::class);
    }

    public function activeHolds(): HasMany
    {
        return $this->holds()->where('expires_at', '>', now());
    }

    public function scopeBookable(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Số chỗ còn trống = sức chứa - đã đặt - đang giữ tạm (HT-01). */
    public function availableCount(?int $activeHolds = null): int
    {
        $holds = $activeHolds ?? $this->activeHolds()->count();

        return max(0, $this->capacity - $this->booked_count - $holds);
    }

    public function timeLabel(): string
    {
        return substr($this->start_time, 0, 5).' - '.substr($this->end_time, 0, 5);
    }
}
