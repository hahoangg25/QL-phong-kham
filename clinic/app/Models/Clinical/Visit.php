<?php

namespace App\Models\Clinical;

use App\Enums\VisitStatus;
use App\Models\Billing\Invoice;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use App\Models\Catalog\Specialty;
use App\Models\Concerns\HasCode;
use App\Models\Scheduling\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Visit extends Model
{
    use HasCode, LogsActivity;

    public const CODE_PREFIX = 'LK';

    protected $attributes = ['is_priority' => false, 'type' => 'appointment'];

    protected $fillable = [
        'code', 'appointment_id', 'patient_id', 'doctor_id', 'specialty_id', 'room_id', 'visit_date',
        'queue_number', 'is_priority', 'queue_sort', 'status', 'type', 'checked_in_by', 'checked_in_at',
        'called_at', 'started_at', 'completed_at', 'reason', 'symptoms', 'pulse', 'bp_systolic', 'bp_diastolic',
        'temperature', 'respiratory_rate', 'spo2', 'weight', 'height', 'clinical_notes', 'conclusion', 'advice',
        'follow_up_date', 'locked_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => VisitStatus::class,
            'visit_date' => 'date',
            'queue_sort' => 'datetime',
            'checked_in_at' => 'datetime',
            'called_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'locked_at' => 'datetime',
            'follow_up_date' => 'date',
            'is_priority' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('medical_record')
            ->logOnly([
                'status', 'doctor_id', 'reason', 'symptoms', 'pulse', 'bp_systolic', 'bp_diastolic', 'temperature',
                'weight', 'clinical_notes', 'conclusion', 'advice', 'follow_up_date', 'locked_at',
            ])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function checkedInBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'checked_in_by');
    }

    public function diagnoses(): HasMany
    {
        return $this->hasMany(Diagnosis::class)->orderByDesc('is_primary')->orderBy('id');
    }

    public function serviceOrders(): HasMany
    {
        return $this->hasMany(ServiceOrder::class);
    }

    public function prescription(): HasOne
    {
        return $this->hasOne(Prescription::class);
    }

    public function invoice(): HasOne
    {
        return $this->hasOne(Invoice::class);
    }

    public function addenda(): HasMany
    {
        return $this->hasMany(VisitAddendum::class)->latest();
    }

    public function review(): HasOne
    {
        return $this->hasOne(Review::class);
    }

    /** Thứ tự hàng đợi: ưu tiên trước, sau đó theo khóa sắp xếp (BR-06, LT-08). */
    public function scopeQueueOrder(Builder $query): Builder
    {
        return $query->orderByDesc('is_priority')->orderBy('queue_sort')->orderBy('queue_number');
    }

    public function scopeToday(Builder $query): Builder
    {
        return $query->whereDate('visit_date', today());
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
    }

    public function isLocked(): bool
    {
        return $this->locked_at !== null;
    }

    /** BR-07: chỉ bác sĩ phụ trách lượt khám được ghi bệnh án. */
    public function isEditableBy(User $user): bool
    {
        return ! $this->isLocked() && $user->doctor && $user->doctor->id === $this->doctor_id;
    }

    public function vitalsSummary(): string
    {
        return collect([
            $this->pulse ? 'Mạch '.$this->pulse.' l/p' : null,
            $this->bp_systolic ? 'HA '.$this->bp_systolic.'/'.$this->bp_diastolic.' mmHg' : null,
            $this->temperature ? 'Nhiệt độ '.$this->temperature.'°C' : null,
            $this->spo2 ? 'SpO2 '.$this->spo2.'%' : null,
            $this->weight ? 'Cân nặng '.$this->weight.' kg' : null,
            $this->height ? 'Chiều cao '.$this->height.' cm' : null,
        ])->filter()->implode(' · ');
    }

    public function bmi(): ?float
    {
        if (! $this->weight || ! $this->height) {
            return null;
        }

        $h = $this->height / 100;

        return round($this->weight / ($h * $h), 1);
    }
}
