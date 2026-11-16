<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicineBatch extends Model
{
    protected $fillable = [
        'medicine_id', 'stock_receipt_id', 'batch_no', 'expiry_date', 'quantity', 'remaining', 'import_price',
    ];

    protected function casts(): array
    {
        return ['expiry_date' => 'date'];
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function receipt(): BelongsTo
    {
        return $this->belongsTo(StockReceipt::class, 'stock_receipt_id');
    }

    public function isExpired(): bool
    {
        return $this->expiry_date->lt(today());
    }

    public function isNearExpiry(): bool
    {
        return ! $this->isExpired() && $this->expiry_date->lte(today()->addDays((int) setting('expiry_warning_days')));
    }
}
