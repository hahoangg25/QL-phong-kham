<?php

namespace App\Models;

use App\Enums\Role;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable
{
    use HasFactory, HasRoles, LogsActivity, Notifiable;

    protected $fillable = [
        'name', 'email', 'phone', 'employee_code', 'avatar', 'password', 'is_active',
        'locked_reason', 'no_show_count', 'consent_at', 'verified_at', 'email_verified_at', 'last_login_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $attributes = [
        'is_active' => true,
        'no_show_count' => 0,
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'verified_at' => 'datetime',
            'consent_at' => 'datetime',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'password' => 'hashed',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('account')
            ->logOnly(['name', 'email', 'phone', 'is_active', 'locked_reason', 'no_show_count'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function doctor(): HasOne
    {
        return $this->hasOne(Doctor::class);
    }

    /** Các hồ sơ bệnh nhân do tài khoản này quản lý (BN-04, BR-09). */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    public function scopeStaff(Builder $query): Builder
    {
        return $query->role(Role::staffValues());
    }

    public function isStaff(): bool
    {
        return $this->hasAnyRole(Role::staffValues());
    }

    public function isPatient(): bool
    {
        return $this->hasRole(Role::Patient->value);
    }

    public function isAdmin(): bool
    {
        return $this->hasRole(Role::Admin->value);
    }

    public function isVerified(): bool
    {
        return $this->verified_at !== null;
    }

    /** BR-05: đã vượt ngưỡng no-show. */
    public function exceedsNoShowThreshold(): bool
    {
        return $this->no_show_count >= (int) setting('no_show_threshold');
    }

    /** BR-05 / HT-05: còn được đặt lịch online không. */
    public function canBookOnline(): bool
    {
        return ! ($this->exceedsNoShowThreshold() && setting('no_show_policy') === 'block');
    }

    /** BR-05 / HT-05: số tiền đặt cọc bắt buộc (0 nếu không cần). */
    public function requiredDeposit(): int
    {
        return $this->exceedsNoShowThreshold() && setting('no_show_policy') === 'deposit'
            ? (int) setting('deposit_amount')
            : 0;
    }

    public function roleLabels(): string
    {
        return $this->getRoleNames()->map(fn ($r) => Role::labelFor($r))->implode(', ');
    }

    public function routeNotificationForSms(): ?string
    {
        return $this->phone;
    }

    public function avatarUrl(): string
    {
        if ($this->doctor?->avatar) {
            return $this->doctor->avatarUrl();
        }

        return $this->avatar
            ? asset($this->avatar)
            : 'https://ui-avatars.com/api/?background=1977cc&color=fff&name='.urlencode($this->name);
    }
}
