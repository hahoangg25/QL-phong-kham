<?php

namespace App\Models\Clinical;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LabResult extends Model
{
    use LogsActivity;

    protected $fillable = [
        'service_order_id', 'performed_by', 'result', 'conclusion', 'attachment_path', 'attachment_name', 'completed_at',
    ];

    protected function casts(): array
    {
        return ['completed_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('medical_record')->logOnly(['result', 'conclusion'])->logOnlyDirty();
    }

    public function serviceOrder(): BelongsTo
    {
        return $this->belongsTo(ServiceOrder::class);
    }

    public function performer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'performed_by');
    }

    public function isImageAttachment(): bool
    {
        return (bool) preg_match('/\.(png|jpe?g|webp|gif)$/i', (string) $this->attachment_path);
    }
}
