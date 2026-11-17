<?php

namespace App\Models\Clinical;

use App\Models\Catalog\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Review extends Model
{
    protected $attributes = ['is_hidden' => false];

    protected $fillable = ['visit_id', 'doctor_id', 'patient_id', 'user_id', 'rating', 'comment', 'is_hidden'];

    protected function casts(): array
    {
        return ['is_hidden' => 'boolean'];
    }

    protected static function booted(): void
    {
        static::saved(fn (Review $r) => $r->doctor?->refreshRating());
        static::deleted(fn (Review $r) => $r->doctor?->refreshRating());
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
