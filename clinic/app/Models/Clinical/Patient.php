<?php

namespace App\Models\Clinical;

use App\Enums\Gender;
use App\Models\Billing\Invoice;
use App\Models\Concerns\HasCode;
use App\Models\Scheduling\Appointment;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Patient extends Model
{
    use HasCode, LogsActivity, SoftDeletes;

    public const CODE_PREFIX = 'BN';

    public const RELATIONSHIPS = ['Bản thân', 'Vợ/Chồng', 'Con', 'Bố', 'Mẹ', 'Ông/Bà', 'Anh/Chị/Em', 'Khác'];

    protected $attributes = ['relationship' => 'Bản thân'];

    protected $fillable = [
        'code', 'user_id', 'relationship', 'full_name', 'date_of_birth', 'gender', 'phone', 'email', 'id_number',
        'insurance_number', 'address', 'occupation', 'blood_type', 'allergies', 'medical_history', 'emergency_contact',
    ];

    protected function casts(): array
    {
        return [
            'date_of_birth' => 'date',
            'gender' => Gender::class,
            // BR-11: mã hóa dữ liệu nhạy cảm
            'allergies' => 'encrypted',
            'medical_history' => 'encrypted',
        ];
    }

    protected static function makeCode(): string
    {
        return self::CODE_PREFIX.str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    }

    public function getActivitylogOptions(): LogOptions
    {
        // Không ghi giá trị trường mã hóa vào log, chỉ ghi nhận có thay đổi
        return LogOptions::defaults()
            ->useLogName('patient')
            ->logOnly(['full_name', 'date_of_birth', 'gender', 'phone', 'email', 'id_number', 'address', 'user_id'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /** LT-10: tìm theo tên, SĐT, mã bệnh nhân, CCCD. */
    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term = trim((string) $term)) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('full_name', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "{$term}%")
            ->orWhere('code', $term)
            ->orWhere('id_number', $term));
    }

    public function getAgeAttribute(): ?int
    {
        return $this->date_of_birth?->age;
    }

    /** LT-08: người cao tuổi hoặc trẻ nhỏ được ưu tiên trong hàng đợi. */
    public function isPriority(): bool
    {
        if ($this->age === null) {
            return false;
        }

        return $this->age >= (int) setting('priority_age_elderly') || $this->age < (int) setting('priority_age_child');
    }

    public function genderLabel(): string
    {
        return $this->gender?->label() ?? '';
    }

    /** Danh sách dị ứng dạng mảng (để cảnh báo khi kê đơn - BR-10). */
    public function allergyList(): array
    {
        return collect(preg_split('/[,;\n]+/', (string) $this->allergies))
            ->map(fn ($a) => trim($a))
            ->filter()
            ->values()
            ->all();
    }

    public function summary(): string
    {
        return collect([
            $this->full_name,
            $this->genderLabel(),
            $this->date_of_birth ? $this->date_of_birth->format('d/m/Y').' ('.$this->age.' tuổi)' : null,
        ])->filter()->implode(' · ');
    }
}
