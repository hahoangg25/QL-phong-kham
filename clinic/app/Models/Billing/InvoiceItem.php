<?php

namespace App\Models\Billing;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InvoiceItem extends Model
{
    public const TYPES = [
        'exam' => 'Phí khám',
        'service' => 'Cận lâm sàng',
        'medicine' => 'Thuốc',
    ];

    protected $fillable = [
        'invoice_id', 'item_type', 'description', 'quantity', 'unit_price', 'amount', 'reference_type', 'reference_id',
    ];

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->item_type] ?? $this->item_type;
    }
}
