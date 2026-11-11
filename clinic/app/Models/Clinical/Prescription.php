<?php

namespace App\Models\Clinical;

use App\Enums\PrescriptionStatus;
use App\Models\Catalog\Doctor;
use App\Models\Concerns\HasCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Prescription extends Model
{
    use HasCode, LogsActivity;

    public const CODE_PREFIX = 'DT';

    protected $fillable = [
        'code', 'visit_id', 'patient_id', 'doctor_id', 'status', 'note', 'dispensed_by', 'dispensed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PrescriptionStatus::class,
            'dispensed_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('medical_record')->logOnly(['status', 'note'])->logOnlyDirty();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispensed_by');
    }

    /** BR-10: đơn đã cấp phát không được sửa. */
    public function isEditable(): bool
    {
        return $this->status === PrescriptionStatus::Pending;
    }

    public function total(): int
    {
        return (int) $this->items->sum('amount');
    }
}
