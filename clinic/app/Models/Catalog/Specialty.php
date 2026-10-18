<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Specialty extends Model
{
    use LogsActivity;

    protected $attributes = ['is_active' => true, 'sort_order' => 0];

    protected $fillable = [
        'name', 'slug', 'icon', 'image', 'short_description', 'description', 'keywords', 'sort_order', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('catalog')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function doctors(): HasMany
    {
        return $this->hasMany(Doctor::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true)->orderBy('sort_order')->orderBy('name');
    }

    public function imageUrl(): string
    {
        return $this->image ? asset($this->image) : asset('assets/img/health/consultation-4.webp');
    }

    public function keywordList(): array
    {
        return collect(explode(',', (string) $this->keywords))
            ->map(fn ($k) => mb_strtolower(trim($k)))
            ->filter()
            ->values()
            ->all();
    }
}
