<?php

namespace App\Models\Scheduling;

use App\Models\Catalog\Specialty;
use App\Models\Clinical\Patient;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SlotHold extends Model
{
    protected $fillable = [
        'token', 'time_slot_id', 'user_id', 'patient_id', 'specialty_id', 'symptoms', 'reschedule_from_id',
        'otp_hash', 'attempts', 'expires_at',
    ];

    protected $hidden = ['otp_hash'];

    protected function casts(): array
    {
        return ['expires_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'token';
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function rescheduleFrom(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'reschedule_from_id');
    }

    public function isExpired(): bool
    {
        return $this->expires_at->isPast();
    }
}
