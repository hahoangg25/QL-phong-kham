<?php

namespace App\Models\Clinical;

use App\Enums\ServiceOrderStatus;
use App\Models\Catalog\Service;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class ServiceOrder extends Model
{
    use LogsActivity;

    protected $fillable = ['visit_id', 'service_id', 'ordered_by', 'price', 'status', 'note', 'started_at'];

    protected function casts(): array
    {
        return [
            'status' => ServiceOrderStatus::class,
            'started_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('medical_record')->logOnly(['status', 'note'])->logOnlyDirty();
    }

    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function orderedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'ordered_by');
    }

    public function result(): HasOne
    {
        return $this->hasOne(LabResult::class);
    }
}
