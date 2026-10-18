<?php

namespace App\Models\Catalog;

use App\Enums\ServiceType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Service extends Model
{
    use LogsActivity;

    protected $attributes = ['is_active' => true, 'unit' => 'lần'];

    protected $fillable = ['code', 'name', 'type', 'specialty_id', 'price', 'unit', 'description', 'is_active'];

    protected function casts(): array
    {
        return [
            'type' => ServiceType::class,
            'is_active' => 'boolean',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('catalog')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function specialty(): BelongsTo
    {
        return $this->belongsTo(Specialty::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Dịch vụ cận lâm sàng (xét nghiệm, CĐHA, thủ thuật). */
    public function scopeParaclinical(Builder $query): Builder
    {
        return $query->where('type', '!=', ServiceType::Exam->value);
    }
}
