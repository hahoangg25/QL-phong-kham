<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\LeaveRequest;
use App\Models\Scheduling\TimeSlot;
use App\Notifications\GeneralNotification;
use App\Services\LeaveService;
use Illuminate\Http\Request;

/** QT-10: duyệt yêu cầu nghỉ của bác sĩ và xử lý lịch hẹn bị ảnh hưởng. */
class LeaveApprovalController extends Controller
{
    public function __construct(protected LeaveService $leaves) {}

    public function index(Request $request)
    {
        $tab = $request->input('tab', 'pending');

        $requests = LeaveRequest::with(['doctor.user', 'doctor.specialty', 'reviewer'])
            ->when($tab === 'pending', fn ($q) => $q->where('status', LeaveStatus::Pending->value))
            ->orderByRaw('CASE WHEN status = ? THEN 0 ELSE 1 END', [LeaveStatus::Pending->value])
            ->latest()
            ->paginate(20)->withQueryString();

        $affected = [];
        foreach ($requests as $leave) {
            $affected[$leave->id] = $leave->affectedAppointments()->count();
        }

        return view('admin.management.leaves.index', [
            'requests' => $requests,
            'tab' => $tab,
            'affected' => $affected,
            'pendingCount' => LeaveRequest::where('status', LeaveStatus::Pending->value)->count(),
        ]);
    }

    public function show(LeaveRequest $leaveRequest)
    {
        $leaveRequest->load(['doctor.user', 'doctor.specialty', 'reviewer']);

        $appointments = $leaveRequest->affectedAppointments()
            ->with(['patient', 'specialty', 'timeSlot'])
            ->orderBy('scheduled_at')
            ->get();

        $alternatives = $appointments->mapWithKeys(fn (Appointment $a) => [$a->id => $this->leaves->alternativeSlots($a)]);

        return view('admin.management.leaves.show', [
            'leave' => $leaveRequest,
            'appointments' => $appointments,
            'alternatives' => $alternatives,
        ]);
    }

    public function approve(Request $request, LeaveRequest $leaveRequest)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $count = $this->leaves->approve($leaveRequest, $request->user(), $note);
        $this->notifyDoctor($leaveRequest, 'Yêu cầu nghỉ đã được duyệt');

        return redirect()->route('staff.admin.leaves.show', $leaveRequest)
            ->with('success', "Đã duyệt yêu cầu nghỉ, khóa {$count} khung giờ. Vui lòng xử lý các lịch hẹn bị ảnh hưởng bên dưới.");
    }

    public function reject(Request $request, LeaveRequest $leaveRequest)
    {
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $this->leaves->reject($leaveRequest, $request->user(), $note);
        $this->notifyDoctor($leaveRequest, 'Yêu cầu nghỉ bị từ chối');

        return redirect()->route('staff.admin.leaves.index')->with('success', 'Đã từ chối yêu cầu nghỉ.');
    }

    /** Đổi lịch hẹn sang slot của bác sĩ khác cùng chuyên khoa. */
    public function reassign(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['time_slot_id' => ['required', 'exists:time_slots,id']]);
        $slot = TimeSlot::with('doctor.user')->findOrFail($data['time_slot_id']);

        $new = $this->leaves->reassign($appointment, $slot, $request->user());

        return back()->with('success', 'Đã chuyển lịch '.$appointment->code.' sang '.$slot->doctor->display_name.' (lịch mới '.$new->code.') và thông báo cho bệnh nhân.');
    }

    public function cancelAppointment(Request $request, Appointment $appointment)
    {
        $this->leaves->cancelAffected($appointment, $request->user());

        return back()->with('success', 'Đã hủy lịch '.$appointment->code.' và thông báo bệnh nhân đặt lại (không tính vắng mặt).');
    }

    protected function notifyDoctor(LeaveRequest $leave, string $title): void
    {
        $user = $leave->doctor?->user;
        if ($user) {
            rescue(fn () => $user->notify(new GeneralNotification(
                $title,
                'Nghỉ từ '.$leave->start_date->format('d/m/Y').' đến '.$leave->end_date->format('d/m/Y')
                    .($leave->review_note ? '. Ghi chú: '.$leave->review_note : ''),
                route('staff.leave.index'),
                'bi-calendar-check',
            )));
        }
    }
}
