<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Room extends Model
{
    use LogsActivity;

    protected $attributes = ['is_active' => true];

    protected $fillable = ['code', 'name', 'floor', 'specialty_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
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
        return $query->where('is_active', true)->orderBy('code');
    }

    public function label(): string
    {
        return trim($this->code.' - '.$this->name.($this->floor ? ' (Tầng '.$this->floor.')' : ''));
    }
}
