<?php

namespace App\Models\Pharmacy;

use App\Models\Concerns\HasCode;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class StockReceipt extends Model
{
    use HasCode;

    public const CODE_PREFIX = 'PN';

    protected $fillable = ['code', 'supplier', 'received_date', 'total_amount', 'note', 'created_by'];

    protected function casts(): array
    {
        return ['received_date' => 'date'];
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
