<?php

namespace App\Http\Controllers\Admin\Doctor;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\TimeSlot;
use App\Models\Scheduling\WorkShift;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** BS-01: lịch làm việc theo tuần/ngày của bác sĩ, số bệnh nhân đã đặt mỗi ca. */
class ScheduleController extends Controller
{
    public function index(Request $request)
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            return view('admin.doctor.schedule.index', ['doctor' => null]);
        }

        try {
            $anchor = Carbon::parse($request->query('week', today()->toDateString()));
        } catch (\Throwable) {
            $anchor = today();
        }
        $weekStart = $anchor->copy()->startOfWeek(Carbon::MONDAY)->startOfDay();
        $weekEnd = $weekStart->copy()->addDays(6);

        $slots = TimeSlot::where('doctor_id', $doctor->id)
            ->whereDate('date', '>=', $weekStart)
            ->whereDate('date', '<=', $weekEnd)
            ->orderBy('start_time')
            ->get()
            ->groupBy(fn (TimeSlot $s) => $s->date->toDateString());

        $appointments = Appointment::with(['patient', 'timeSlot'])
            ->where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $weekStart)
            ->whereDate('appointment_date', '<=', $weekEnd)
            ->orderBy('scheduled_at')
            ->get();

        $countable = array_merge(AppointmentStatus::activeValues(), [AppointmentStatus::Completed->value]);
        $appointmentsByDay = $appointments->groupBy(fn (Appointment $a) => $a->appointment_date->toDateString());

        $days = collect(range(0, 6))->map(function (int $i) use ($weekStart, $slots, $appointmentsByDay, $countable) {
            $date = $weekStart->copy()->addDays($i);
            $key = $date->toDateString();
            $daySlots = $slots->get($key, collect());

            // Gom slot theo ca (sáng/chiều) để hiển thị gọn
            $sessions = $daySlots->groupBy(fn (TimeSlot $s) => (int) substr($s->start_time, 0, 2) < 12 ? 'Sáng' : 'Chiều')
                ->map(fn ($items) => [
                    'range' => substr($items->first()->start_time, 0, 5).' - '.substr($items->last()->end_time, 0, 5),
                    'booked' => $items->sum('booked_count'),
                    'capacity' => $items->sum('capacity'),
                    'inactive' => $items->where('is_active', false)->count(),
                    'slots' => $items,
                ]);

            return [
                'date' => $date,
                'sessions' => $sessions,
                'appointment_count' => $appointmentsByDay->get($key, collect())
                    ->filter(fn (Appointment $a) => in_array($a->status->value, $countable, true))->count(),
            ];
        });

        try {
            $selected = Carbon::parse($request->query('day', today()->between($weekStart, $weekEnd) ? today()->toDateString() : $weekStart->toDateString()));
        } catch (\Throwable) {
            $selected = $weekStart->copy();
        }
        $dayAppointments = $appointmentsByDay->get($selected->toDateString(), collect());

        $shifts = WorkShift::with('room')->where('doctor_id', $doctor->id)->where('is_active', true)
            ->orderBy('weekday')->orderBy('start_time')->get()
            ->sortBy(fn (WorkShift $s) => ($s->weekday === 0 ? 7 : $s->weekday).$s->start_time);

        $leaves = $doctor->leaveRequests()->latest()->take(5)->get();

        return view('admin.doctor.schedule.index', compact(
            'doctor', 'weekStart', 'weekEnd', 'days', 'selected', 'dayAppointments', 'shifts', 'leaves'
        ));
    }
}
