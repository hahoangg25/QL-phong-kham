<?php

namespace App\Models\Billing;

use App\Enums\InvoiceStatus;
use App\Enums\PaymentMethod;
use App\Models\Clinical\Patient;
use App\Models\Clinical\Visit;
use App\Models\Concerns\HasCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Invoice extends Model
{
    use HasCode, LogsActivity;

    public const CODE_PREFIX = 'HD';

    protected $attributes = ['subtotal' => 0, 'discount' => 0, 'deposit_deducted' => 0, 'total' => 0, 'paid_amount' => 0];

    protected $fillable = [
        'code', 'visit_id', 'patient_id', 'status', 'subtotal', 'discount', 'deposit_deducted', 'total',
        'paid_amount', 'payment_method', 'note', 'created_by', 'cashier_id', 'paid_at', 'cancelled_by',
        'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => InvoiceStatus::class,
            'payment_method' => PaymentMethod::class,
            'paid_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('billing')
            ->logOnly(['status', 'total', 'paid_amount', 'payment_method', 'cancel_reason'])
            ->logOnlyDirty();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function cashier(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cashier_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function canceller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by');
    }

    public function scopeOwnedBy(Builder $query, User $user): Builder
    {
        return $query->whereHas('patient', fn ($q) => $q->where('user_id', $user->id));
    }

    public function amountDue(): int
    {
        return max(0, (int) $this->total - (int) $this->paid_amount);
    }
}
