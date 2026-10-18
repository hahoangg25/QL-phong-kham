<?php

namespace App\Models\Catalog;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class Icd10Code extends Model
{
    protected $fillable = ['code', 'name', 'name_en', 'chapter'];

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('code', 'like', $term.'%')
            ->orWhere('name', 'like', '%'.$term.'%')
            ->orWhere('name_en', 'like', '%'.$term.'%'));
    }
}
