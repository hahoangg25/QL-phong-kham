<?php

namespace App\Http\Controllers\Admin\Doctor;

use App\Enums\AppointmentStatus;
use App\Enums\LeaveStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Scheduling\LeaveRequest;
use Illuminate\Http\Request;

/** BS-02: bác sĩ gửi yêu cầu nghỉ/đổi ca để quản trị duyệt (QT-10). */
class LeaveRequestController extends Controller
{
    public function index(Request $request)
    {
        $doctor = $request->user()->doctor;

        if (! $doctor) {
            return view('admin.doctor.leave.index', ['doctor' => null, 'leaves' => collect()]);
        }

        $leaves = $doctor->leaveRequests()->with('reviewer')->latest()->paginate(15);

        return view('admin.doctor.leave.index', compact('doctor', 'leaves'));
    }

    public function store(Request $request)
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor, 403, 'Tài khoản chưa có hồ sơ bác sĩ.');

        $data = $request->validate([
            'start_date' => ['required', 'date', 'after_or_equal:today'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'reason' => ['required', 'string', 'max:500'],
        ]);

        $overlap = LeaveRequest::where('doctor_id', $doctor->id)
            ->whereIn('status', [LeaveStatus::Pending->value, LeaveStatus::Approved->value])
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($overlap) {
            return back()->withInput()->with('error', 'Khoảng thời gian này trùng với một yêu cầu nghỉ đang chờ duyệt hoặc đã duyệt.');
        }

        LeaveRequest::create($data + ['doctor_id' => $doctor->id, 'status' => LeaveStatus::Pending]);

        $affected = Appointment::where('doctor_id', $doctor->id)
            ->whereDate('appointment_date', '>=', $data['start_date'])
            ->whereDate('appointment_date', '<=', $data['end_date'])
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->count();

        return redirect()->route('staff.leave.index')->with('success', 'Đã gửi yêu cầu nghỉ, chờ quản trị viên duyệt.'
            .($affected ? " Có {$affected} lịch hẹn trong thời gian này sẽ được quản trị xử lý (đổi bác sĩ hoặc báo bệnh nhân)." : ''));
    }

    public function destroy(Request $request, LeaveRequest $leaveRequest)
    {
        $doctor = $request->user()->doctor;
        abort_unless($doctor && $leaveRequest->doctor_id === $doctor->id, 403);

        if ($leaveRequest->status !== LeaveStatus::Pending) {
            return back()->with('error', 'Chỉ rút lại được yêu cầu đang chờ duyệt.');
        }

        $leaveRequest->delete();

        return back()->with('success', 'Đã rút lại yêu cầu nghỉ.');
    }
}
