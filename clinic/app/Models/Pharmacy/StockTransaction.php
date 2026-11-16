<?php

namespace App\Models\Pharmacy;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class StockTransaction extends Model
{
    public const UPDATED_AT = null;

    public const TYPES = [
        'import' => 'Nhập kho',
        'dispense' => 'Cấp phát',
        'dispose' => 'Xuất hủy',
        'adjust' => 'Điều chỉnh kiểm kê',
    ];

    protected $fillable = [
        'medicine_id', 'medicine_batch_id', 'type', 'quantity', 'reference_type', 'reference_id', 'note', 'user_id',
    ];

    public function medicine(): BelongsTo
    {
        return $this->belongsTo(Medicine::class);
    }

    public function batch(): BelongsTo
    {
        return $this->belongsTo(MedicineBatch::class, 'medicine_batch_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type] ?? $this->type;
    }
}
