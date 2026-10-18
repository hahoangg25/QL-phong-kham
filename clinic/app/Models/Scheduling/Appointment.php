<?php

namespace App\Models\Scheduling;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Billing\Payment;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Specialty;
use App\Models\Clinical\Patient;
use App\Models\Clinical\Visit;
use App\Models\Concerns\HasCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Appointment extends Model
{
    use HasCode, LogsActivity;

    public const CODE_PREFIX = 'LH';

    public const SOURCES = [
        'online' => 'Đặt online',
        'phone' => 'Lễ tân đặt hộ',
        'follow_up' => 'Hẹn tái khám',
    ];

    protected $attributes = ['source' => 'online', 'deposit_required' => 0, 'prepaid_amount' => 0, 'is_late_cancel' => false];

    protected $fillable = [
        'code', 'patient_id', 'booked_by', 'doctor_id', 'specialty_id', 'time_slot_id', 'appointment_date',
        'scheduled_at', 'status', 'source', 'symptoms', 'note', 'deposit_required', 'prepaid_amount',
        'confirmed_by', 'confirmed_at', 'checked_in_at', 'cancelled_by', 'cancelled_at', 'cancel_reason',
        'is_late_cancel', 'rescheduled_from_id', 'rescheduled_to_id', 'reminded_24h_at', 'reminded_2h_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => AppointmentStatus::class,
            'appointment_date' => 'date',
            'scheduled_at' => 'datetime',
            'confirmed_at' => 'datetime',
            'checked_in_at' => 'datetime',
            'cancelled_at' => 'datetime',
            'reminded_24h_at' => 'datetime',
            'reminded_2h_at' => 'datetime',
            'is_late_cancel' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('appointment')
            ->logOnly(['status', 'time_slot_id', 'doctor_id', 'scheduled_at', 'cancel_reason'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function bookedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'booked_by');
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
    }

    public function visit(): HasOne
    {
        return $this->hasOne(Visit::class);
    }

    public function statusLogs(): HasMany
    {
        return $this->hasMany(AppointmentStatusLog::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function rescheduledFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_from_id');
    }

    public function rescheduledTo(): BelongsTo
    {
        return $this->belongsTo(self::class, 'rescheduled_to_id');
    }

    public function confirmedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'confirmed_by');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', AppointmentStatus::activeValues());
    }

    public function scopeUpcoming(Builder $query): Builder
    {
        return $query->active()->where('scheduled_at', '>=', now()->subHours(2))->orderBy('scheduled_at');
    }

    /** BR-09: các lịch hẹn của những hồ sơ mà tài khoản quản lý. */
    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
    }

    /** BR-04: hủy lúc này có bị tính trễ (no-show) không. */
    public function isLateCancellationNow(): bool
    {
        return now()->diffInMinutes($this->scheduled_at, false) < (int) setting('cancel_free_hours_before') * 60;
    }

    public function successfulPaymentsTotal(): int
    {
        return (int) $this->payments()->where('status', PaymentStatus::Success->value)->sum('amount');
    }

    /** Số tiền còn có thể thanh toán online trước (phí khám/đặt cọc) - BN-10. */
    public function payableOnlineAmount(): int
    {
        $target = max($this->deposit_required, (int) $this->doctor?->consultation_fee);

        return max(0, $target - $this->prepaid_amount);
    }

    public function timeLabel(): string
    {
        return $this->scheduled_at->format('H:i').' · '.$this->scheduled_at->translatedFormat('l, d/m/Y');
    }
}
