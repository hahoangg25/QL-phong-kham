<?php

namespace App\Http\Controllers\Admin\Management;

use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use App\Models\Scheduling\TimeSlot;
use App\Models\Scheduling\WorkShift;
use App\Services\SlotGenerator;
use Carbon\Carbon;
use Illuminate\Http\Request;

/** QT-08: cấu hình ca làm việc; QT-09: sinh slot tự động; quản lý khung giờ. */
class WorkShiftController extends Controller
{
    public function index(Request $request)
    {
        $doctors = Doctor::with(['user', 'specialty'])->orderBy('specialty_id')->get();
        $doctor = $request->filled('doctor_id')
            ? $doctors->firstWhere('id', $request->integer('doctor_id'))
            : $doctors->first();

        $shifts = $doctor
            ? WorkShift::with('room')->where('doctor_id', $doctor->id)->orderBy('start_time')->get()->groupBy('weekday')
            : collect();

        $upcomingSlots = $doctor
            ? TimeSlot::where('doctor_id', $doctor->id)->whereDate('date', '>=', today())->count()
            : 0;
        $lastSlotDate = $doctor
            ? TimeSlot::where('doctor_id', $doctor->id)->max('date')
            : null;

        return view('admin.management.shifts.index', [
            'doctors' => $doctors,
            'doctor' => $doctor,
            'shifts' => $shifts,
            'rooms' => Room::active()->get(),
            'weekdays' => WorkShift::WEEKDAYS,
            'upcomingSlots' => $upcomingSlots,
            'lastSlotDate' => $lastSlotDate ? Carbon::parse($lastSlotDate) : null,
            'defaultDays' => (int) setting('slot_generate_days'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        WorkShift::create($data);

        return redirect()->route('staff.admin.shifts.index', ['doctor_id' => $data['doctor_id']])
            ->with('success', 'Đã thêm ca '.WorkShift::WEEKDAYS[$data['weekday']].' '.$data['start_time'].' - '.$data['end_time'].'. Bấm "Sinh slot" để tạo khung giờ đặt lịch.');
    }

    public function update(Request $request, WorkShift $workShift)
    {
        // Bật/tắt nhanh
        if ($request->has('toggle')) {
            $workShift->update(['is_active' => ! $workShift->is_active]);

            return back()->with('success', 'Đã '.($workShift->is_active ? 'kích hoạt' : 'tạm ngưng').' ca '.$workShift->weekdayLabel().' '.$workShift->timeRange().'.');
        }

        $data = $this->validated($request, $workShift);
        $workShift->update($data);

        return redirect()->route('staff.admin.shifts.index', ['doctor_id' => $workShift->doctor_id])
            ->with('success', 'Đã cập nhật ca làm việc. Thay đổi áp dụng cho các slot được sinh mới.');
    }

    public function destroy(WorkShift $workShift)
    {
        $doctorId = $workShift->doctor_id;
        $workShift->delete();

        return redirect()->route('staff.admin.shifts.index', ['doctor_id' => $doctorId])
            ->with('success', 'Đã xóa ca làm việc. Các slot đã sinh trước đó vẫn được giữ nguyên.');
    }

    /** QT-09: sinh slot cho N ngày tới. */
    public function generate(Request $request, SlotGenerator $generator)
    {
        $data = $request->validate([
            'days' => ['required', 'integer', 'min:1', 'max:60'],
            'doctor_id' => ['nullable', 'exists:doctors,id'],
        ]);

        $count = $generator->generate(today(), (int) $data['days'], $data['doctor_id'] ?? null);

        return back()->with($count ? 'success' : 'info', $count
            ? "Đã sinh {$count} khung giờ mới cho {$data['days']} ngày tới."
            : 'Không có khung giờ mới nào được sinh (các slot đã tồn tại hoặc chưa có ca làm việc phù hợp).');
    }

    public function slots(Request $request)
    {
        $doctors = Doctor::with(['user', 'specialty'])->orderBy('specialty_id')->get();
        $date = $request->filled('date') ? Carbon::parse($request->input('date')) : today();

        $slots = TimeSlot::with(['doctor.user', 'doctor.specialty', 'room'])
            ->whereDate('date', $date)
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->integer('doctor_id')))
            ->orderBy('doctor_id')->orderBy('start_time')
            ->get()
            ->groupBy('doctor_id');

        return view('admin.management.shifts.slots', compact('doctors', 'date', 'slots'));
    }

    public function toggleSlot(TimeSlot $timeSlot)
    {
        $timeSlot->update(['is_active' => ! $timeSlot->is_active]);

        $message = 'Đã '.($timeSlot->is_active ? 'mở' : 'khóa').' khung giờ '.$timeSlot->timeLabel().' ngày '.$timeSlot->date->format('d/m/Y').'.';
        if (! $timeSlot->is_active && $timeSlot->booked_count > 0) {
            return back()->with('warning', $message.' Lưu ý: '.$timeSlot->booked_count.' lịch hẹn đã đặt trong khung giờ này vẫn được giữ nguyên.');
        }

        return back()->with('success', $message);
    }

    protected function validated(Request $request, ?WorkShift $shift = null): array
    {
        $data = $request->validate([
            'doctor_id' => [$shift ? 'nullable' : 'required', 'exists:doctors,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'weekday' => ['required', 'integer', 'between:0,6'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'slot_minutes' => ['required', 'integer', 'min:10', 'max:120'],
            'max_patients_per_slot' => ['required', 'integer', 'min:1', 'max:20'],
            'effective_from' => ['nullable', 'date'],
            'effective_to' => ['nullable', 'date', 'after_or_equal:effective_from'],
        ], ['end_time.after' => 'Giờ kết thúc phải sau giờ bắt đầu.']);

        if ($shift) {
            $data['doctor_id'] = $shift->doctor_id;
        }
        $data['is_active'] = $request->boolean('is_active', true);

        // Không cho chồng giờ với ca khác của cùng bác sĩ trong cùng thứ
        $overlap = WorkShift::where('doctor_id', $data['doctor_id'])
            ->where('weekday', $data['weekday'])
            ->when($shift, fn ($q) => $q->where('id', '!=', $shift->id))
            ->get()
            ->contains(fn (WorkShift $s) => substr($s->start_time, 0, 5) < $data['end_time'] && substr($s->end_time, 0, 5) > $data['start_time']);

        if ($overlap) {
            throw \Illuminate\Validation\ValidationException::withMessages([
                'start_time' => 'Ca làm việc bị trùng giờ với một ca khác của bác sĩ trong cùng ngày.',
            ]);
        }

        return $data;
    }
}
