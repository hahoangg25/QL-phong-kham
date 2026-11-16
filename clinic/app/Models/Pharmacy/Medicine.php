<?php

namespace App\Models\Pharmacy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Medicine extends Model
{
    use LogsActivity;

    protected $attributes = ['is_active' => true, 'min_stock' => 0, 'route' => 'Uống', 'stock_quantity' => 0];

    protected $fillable = [
        'code', 'name', 'active_ingredient', 'strength', 'dosage_form', 'unit', 'route', 'price', 'stock_quantity', 'min_stock',
        'manufacturer', 'is_active',
    ];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('pharmacy')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function batches(): HasMany
    {
        return $this->hasMany(MedicineBatch::class);
    }

    /** Lô còn dùng được: còn hàng và chưa hết hạn, sắp xếp FEFO. */
    public function usableBatches(): HasMany
    {
        return $this->batches()
            ->where('remaining', '>', 0)
            ->whereDate('expiry_date', '>=', today())
            ->orderBy('expiry_date')
            ->orderBy('id');
    }

    public function stockTransactions(): HasMany
    {
        return $this->hasMany(StockTransaction::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** Thêm cột usable_stock (tồn kho thực tế) vào truy vấn. */
    public function scopeWithUsableStock(Builder $query): Builder
    {
        return $query->selectRaw('medicines.*, medicines.stock_quantity as usable_stock');
    }

    public function scopeSearch(Builder $query, ?string $term): Builder
    {
        if (! $term) {
            return $query;
        }

        return $query->where(fn ($q) => $q->where('name', 'like', "%{$term}%")
            ->orWhere('active_ingredient', 'like', "%{$term}%")
            ->orWhere('code', $term));
    }

    public function usableStock(): int
    {
        return (int) ($this->stock_quantity ?? 0);
    }

    public function isLowStock(): bool
    {
        return $this->usableStock() < $this->min_stock;
    }

    public function fullName(): string
    {
        return trim($this->name.' '.$this->strength);
    }
}
