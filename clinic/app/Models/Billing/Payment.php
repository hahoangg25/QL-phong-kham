<?php

namespace App\Models\Billing;

use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Payment extends Model
{
    use LogsActivity;

    public const TYPES = [
        'payment' => 'Thanh toán hóa đơn',
        'deposit' => 'Đặt cọc / trả trước',
        'refund' => 'Hoàn tiền',
    ];

    protected $fillable = [
        'code', 'invoice_id', 'appointment_id', 'patient_id', 'type', 'method', 'amount', 'status',
        'transaction_no', 'gateway_payload', 'note', 'received_by', 'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'gateway_payload' => 'array',
            'paid_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Payment $p) {
            $p->code ??= 'TT'.now()->format('ymdHis').Str::upper(Str::random(4));
        });
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('billing')->logOnly(['status', 'amount', 'method'])->logOnlyDirty();
    }

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class)->withTrashed();
    }

    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
