<?php

namespace App\Models\Clinical;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Diagnosis extends Model
{
    protected $fillable = ['visit_id', 'icd10_code', 'name', 'is_primary', 'note'];

    protected function casts(): array
    {
        return ['is_primary' => 'boolean'];
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function label(): string
    {
        return $this->icd10_code.' - '.$this->name;
    }
}
