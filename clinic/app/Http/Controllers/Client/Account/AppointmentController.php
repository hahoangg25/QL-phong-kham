<?php

namespace App\Http\Controllers\Client\Account;

use App\Enums\AppointmentStatus;
use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Scheduling\TimeSlot;
use App\Services\BookingService;
use App\Services\QueueService;
use App\Support\QrCode;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** BN-11 xem lịch hẹn, BN-12 hủy, BN-13 đổi lịch, BN-18 theo dõi hàng đợi. */
class AppointmentController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(
        protected BookingService $booking,
        protected QueueService $queue,
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();
        $tab = in_array($request->input('tab'), ['sap-toi', 'da-qua', 'tat-ca'], true) ? $request->input('tab') : 'sap-toi';
        $patientId = $request->integer('patient_id') ?: null;

        $query = Appointment::with(['patient', 'doctor.user', 'specialty'])
            ->ownedBy($user)
            ->when($patientId, fn ($q) => $q->where('patient_id', $patientId));

        match ($tab) {
            'sap-toi' => $query->upcoming(),
            'da-qua' => $query->where(fn ($q) => $q
                ->whereNotIn('status', AppointmentStatus::activeValues())
                ->orWhere('scheduled_at', '<', now()->subHours(2)))
                ->orderByDesc('scheduled_at'),
            default => $query->orderByDesc('scheduled_at'),
        };

        return view('client.account.appointments.index', [
            'appointments' => $query->paginate(10)->withQueryString(),
            'tab' => $tab,
            'patients' => $user->patients()->orderBy('id')->get(),
            'patientId' => $patientId,
        ]);
    }

    public function show(Appointment $appointment)
    {
        $this->authorizeOwner($appointment);

        $appointment->load([
            'patient', 'doctor.user', 'doctor.room', 'specialty', 'timeSlot.room', 'visit.room',
            'statusLogs.user', 'rescheduledFrom', 'rescheduledTo', 'payments',
        ]);

        $visit = $appointment->visit;

        return view('client.account.appointments.show', [
            'appointment' => $appointment,
            'qr' => QrCode::svg($appointment->code, 180),
            'lateCancel' => $appointment->status === AppointmentStatus::Confirmed && $appointment->isLateCancellationNow(),
            'canPay' => in_array($appointment->status, AppointmentStatus::active(), true) && $appointment->payableOnlineAmount() > 0,
            'showQueue' => $visit && $visit->visit_date->isToday(),
            'cancelHours' => (int) setting('cancel_free_hours_before'),
        ]);
    }

    /** BN-18: số thứ tự và số người còn trước (JSON cho tự làm mới). */
    public function queue(Appointment $appointment): JsonResponse
    {
        $this->authorizeOwner($appointment);
        $visit = $appointment->visit()->with(['room', 'doctor.room'])->first();

        if (! $visit) {
            return response()->json([
                'status' => $appointment->status->value,
                'status_label' => $appointment->status->label(),
                'queue_number' => null,
                'people_ahead' => null,
                'room' => null,
            ]);
        }

        return response()->json([
            'status' => $visit->status->value,
            'status_label' => $visit->status->label(),
            'queue_number' => $visit->queue_number,
            'people_ahead' => $this->queue->peopleAhead($visit),
            'room' => ($visit->room ?? $visit->doctor->room)?->label(),
        ]);
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $this->authorizeOwner($appointment);
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:500']]);

        $this->booking->cancel($appointment, $request->user(), $data['reason'] ?? 'Bệnh nhân hủy lịch');

        $message = 'Đã hủy lịch hẹn '.$appointment->code.'.';
        if ($appointment->is_late_cancel) {
            $message .= ' Do hủy sát giờ khám (dưới '.setting('cancel_free_hours_before').' giờ), hệ thống tính 1 lần vắng mặt (BR-04).';
        }

        return redirect()->route('account.appointments.show', $appointment)->with('success', $message);
    }

    public function rescheduleForm(Appointment $appointment)
    {
        $this->authorizeOwner($appointment);

        if (! $appointment->status->isCancellable()) {
            return redirect()->route('account.appointments.show', $appointment)
                ->with('error', 'Lịch hẹn ở trạng thái "'.$appointment->status->label().'" không thể đổi.');
        }

        $appointment->load(['patient', 'doctor.user', 'specialty']);
        $doctors = Doctor::with(['user', 'specialty'])->active()->where('specialty_id', $appointment->specialty_id)->get();

        return view('client.account.appointments.reschedule', [
            'appointment' => $appointment,
            'doctorsJson' => $doctors->map(fn (Doctor $d) => [
                'id' => $d->id,
                'name' => $d->display_name,
                'specialty_id' => $d->specialty_id,
                'specialty' => $d->specialty->name,
                'avatar' => $d->avatarUrl(),
                'fee' => $d->consultation_fee,
                'rating' => $d->rating_avg,
                'rating_count' => $d->rating_count,
                'experience' => $d->experience_years,
                'position' => $d->position,
            ])->values(),
            'rules' => [
                'min_hours' => (int) setting('booking_min_hours_before'),
                'max_days' => (int) setting('booking_max_days_ahead'),
                'hold_minutes' => (int) setting('slot_hold_minutes'),
            ],
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment)
    {
        $this->authorizeOwner($appointment);

        $data = $request->validate([
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'symptoms' => ['nullable', 'string', 'max:1000'],
        ], ['time_slot_id.required' => 'Vui lòng chọn khung giờ mới.']);

        if (! $appointment->status->isCancellable()) {
            return back()->with('error', 'Lịch hẹn không còn đổi được.');
        }

        $slot = TimeSlot::with('doctor')->findOrFail($data['time_slot_id']);

        if ($slot->id === $appointment->time_slot_id) {
            return back()->with('error', 'Vui lòng chọn khung giờ khác với lịch hiện tại.');
        }
        if ($slot->doctor->specialty_id !== $appointment->specialty_id) {
            return back()->with('error', 'Chỉ đổi được sang khung giờ cùng chuyên khoa.');
        }

        $hold = $this->booking->hold(
            $request->user(),
            $appointment->patient,
            $slot,
            $data['symptoms'] ?? $appointment->symptoms,
            $appointment,
        );

        return redirect()->route('account.booking.verify', $hold)
            ->with('success', 'Đã giữ khung giờ mới. Nhập mã OTP để xác nhận đổi lịch.');
    }
}
