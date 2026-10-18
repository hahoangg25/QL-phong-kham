<?php

namespace App\Models\Catalog;

use App\Models\Clinical\Review;
use App\Models\Clinical\Visit;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Scheduling\TimeSlot;
use App\Models\Scheduling\WorkShift;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Doctor extends Model
{
    use LogsActivity;

    protected $attributes = ['is_active' => true, 'rating_avg' => 0, 'rating_count' => 0];

    protected $fillable = [
        'user_id', 'specialty_id', 'room_id', 'title', 'position', 'experience_years', 'consultation_fee',
        'avatar', 'short_bio', 'bio', 'rating_avg', 'rating_count', 'is_active',
    ];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'rating_avg' => 'float',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('catalog')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function workShifts(): HasMany
    {
        return $this->hasMany(WorkShift::class);
    }

    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function leaveRequests(): HasMany
    {
        return $this->hasMany(LeaveRequest::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('doctors.is_active', true);
    }

    public function getNameAttribute(): string
    {
        return $this->user?->name ?? '';
    }

    /** Tên kèm học hàm, học vị: "ThS.BS Nguyễn Văn A". */
    public function getDisplayNameAttribute(): string
    {
        return trim(($this->title ? $this->title.' ' : '').$this->name);
    }

    public function avatarUrl(): string
    {
        return $this->avatar
            ? asset($this->avatar)
            : 'https://ui-avatars.com/api/?background=1977cc&color=fff&size=300&name='.urlencode($this->name);
    }

    /** Cập nhật điểm đánh giá trung bình (BN-19). */
    public function refreshRating(): void
    {
        $stats = $this->reviews()->where('is_hidden', false)
            ->selectRaw('COUNT(*) as c, AVG(rating) as a')->first();

        $this->forceFill([
            'rating_count' => (int) $stats->c,
            'rating_avg' => round((float) $stats->a, 2),
        ])->saveQuietly();
    }
}
