<?php

namespace App\Models\Scheduling;

use App\Enums\LeaveStatus;
use App\Models\Catalog\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class LeaveRequest extends Model
{
    use LogsActivity;

    protected $fillable = [
        'doctor_id', 'start_date', 'end_date', 'reason', 'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'start_date' => 'date',
            'end_date' => 'date',
            'reviewed_at' => 'datetime',
            'status' => LeaveStatus::class,
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->useLogName('schedule')->logFillable()->logOnlyDirty()->dontSubmitEmptyLogs();
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /** QT-10: các lịch hẹn còn hiệu lực bị ảnh hưởng bởi đợt nghỉ. */
    public function affectedAppointments()
    {
        return Appointment::query()
            ->where('doctor_id', $this->doctor_id)
            ->whereDate('appointment_date', '>=', $this->start_date)
            ->whereDate('appointment_date', '<=', $this->end_date)
            ->whereIn('status', [\App\Enums\AppointmentStatus::Pending->value, \App\Enums\AppointmentStatus::Confirmed->value]);
    }
}
