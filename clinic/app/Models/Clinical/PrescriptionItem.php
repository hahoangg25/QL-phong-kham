<?php

namespace App\Models\Clinical;

use App\Models\Pharmacy\Medicine;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionItem extends Model
{
    protected $fillable = ['prescription_id', 'medicine_id', 'quantity', 'dosage', 'usage', 'days', 'unit_price', 'amount'];

    public function prescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class);
    }

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }
}
