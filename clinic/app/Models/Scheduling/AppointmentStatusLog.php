<?php

namespace App\Models\Scheduling;

use App\Enums\AppointmentStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AppointmentStatusLog extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = ['appointment_id', 'from_status', 'to_status', 'user_id', 'note'];

    protected function casts(): array
    {
        return [
            'from_status' => AppointmentStatus::class,
            'to_status' => AppointmentStatus::class,
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
