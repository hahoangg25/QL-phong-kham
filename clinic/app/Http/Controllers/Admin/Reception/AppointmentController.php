<?php

namespace App\Http\Controllers\Admin\Reception;

use App\Enums\AppointmentStatus;
use App\Enums\Gender;
use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\TimeSlot;
use App\Services\BookingService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * Lễ tân quản lý lịch hẹn: danh sách trong ngày (LT-01), xác nhận/từ chối (LT-02),
 * đặt lịch hộ (LT-05), hủy/đổi lịch hộ (LT-06).
 */
class AppointmentController extends Controller
{
    public function __construct(protected BookingService $booking) {}

    public function index(Request $request)
    {
        // Mặc định xem hôm nay; để trống ô ngày = tất cả lịch từ hôm nay trở đi
        $date = $request->has('date') ? $request->input('date') : today()->toDateString();

        $base = Appointment::query()
            ->when($date, fn ($q) => $q->whereDate('appointment_date', $date))
            ->when(! $date, fn ($q) => $q->where('scheduled_at', '>=', today()))
            ->when($request->filled('doctor_id'), fn ($q) => $q->where('doctor_id', $request->integer('doctor_id')))
            ->when($request->filled('specialty_id'), fn ($q) => $q->where('specialty_id', $request->integer('specialty_id')))
            ->when($request->filled('q'), function ($q) use ($request) {
                $term = trim($request->input('q'));
                $q->where(fn ($w) => $w->where('code', $term)
                    ->orWhereHas('patient', fn ($p) => $p->where('full_name', 'like', "%{$term}%")->orWhere('phone', 'like', "{$term}%")));
            });

        $statusCounts = (clone $base)->selectRaw('status, COUNT(*) as c')->groupBy('status')->pluck('c', 'status');

        $appointments = $base
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->input('status')))
            ->with(['patient', 'doctor.user', 'specialty', 'visit'])
            ->orderBy('scheduled_at')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reception.appointments.index', [
            'appointments' => $appointments,
            'statusCounts' => $statusCounts,
            'date' => $date,
            'doctors' => Doctor::with('user')->active()->get()->sortBy('name'),
            'specialties' => Specialty::active()->get(),
            'statuses' => AppointmentStatus::options(),
        ]);
    }

    public function show(Appointment $appointment)
    {
        $appointment->load([
            'patient.user', 'doctor.user', 'specialty', 'timeSlot.room', 'statusLogs.user', 'visit',
            'payments', 'bookedBy', 'confirmedBy', 'cancelledBy', 'rescheduledFrom', 'rescheduledTo',
        ]);

        return view('admin.reception.appointments.show', compact('appointment'));
    }

    public function create(Request $request)
    {
        return view('admin.reception.appointments.create', [
            'specialties' => Specialty::active()->get(),
            'doctors' => $this->doctorOptions(),
            'genders' => Gender::options(),
            'patient' => $request->filled('patient_id') ? Patient::find($request->integer('patient_id')) : null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'full_name' => ['required_without:patient_id', 'nullable', 'string', 'max:100'],
            'phone' => ['required_without:patient_id', 'nullable', 'regex:/^0\d{9}$/'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'symptoms' => ['nullable', 'string', 'max:1000'],
        ], [
            'full_name.required_without' => 'Vui lòng chọn bệnh nhân có sẵn hoặc nhập họ tên bệnh nhân mới.',
            'phone.required_without' => 'Vui lòng nhập số điện thoại bệnh nhân mới.',
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
            'time_slot_id.required' => 'Vui lòng chọn khung giờ khám.',
        ]);

        // Tạo hồ sơ mới (nếu có) và đặt lịch trong cùng transaction: vi phạm quy tắc thì không để lại hồ sơ rác
        $appointment = DB::transaction(function () use ($data, $request) {
            $patient = ! empty($data['patient_id'])
                ? Patient::findOrFail($data['patient_id'])
                : Patient::create([
                    'full_name' => $data['full_name'],
                    'phone' => $data['phone'],
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'gender' => $data['gender'] ?? null,
                ]);

            return $this->booking->bookByStaff(
                $request->user(),
                $patient,
                TimeSlot::findOrFail($data['time_slot_id']),
                $data['symptoms'] ?? null,
                'phone',
            );
        });

        return redirect()->route('staff.appointments.show', $appointment)
            ->with('success', 'Đã đặt lịch '.$appointment->code.' cho '.$appointment->patient->full_name.' lúc '.$appointment->scheduled_at->format('H:i d/m/Y').'.');
    }

    public function confirm(Request $request, Appointment $appointment)
    {
        $this->booking->confirm($appointment, $request->user());

        return back()->with('success', 'Đã xác nhận lịch hẹn '.$appointment->code.'.');
    }

    public function reject(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:300']], [
            'reason.required' => 'Vui lòng nhập lý do từ chối.',
        ]);

        $this->booking->reject($appointment, $request->user(), $data['reason']);

        return back()->with('success', 'Đã từ chối lịch hẹn '.$appointment->code.'.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        $this->booking->cancel(
            $appointment,
            $request->user(),
            'Lễ tân hủy hộ'.(! empty($data['reason']) ? ': '.$data['reason'] : ''),
            countNoShow: $request->boolean('count_no_show'),
        );

        return back()->with('success', 'Đã hủy lịch hẹn '.$appointment->code.'.'
            .($appointment->is_late_cancel ? ' Lần hủy này được tính 1 lần vắng mặt (BR-04).' : ''));
    }

    public function rescheduleForm(Appointment $appointment)
    {
        if (! $appointment->status->isCancellable()) {
            return redirect()->route('staff.appointments.show', $appointment)
                ->with('error', 'Lịch hẹn ở trạng thái "'.$appointment->status->label().'" không thể đổi.');
        }

        $appointment->load(['patient', 'doctor.user', 'specialty']);

        return view('admin.reception.appointments.reschedule', [
            'appointment' => $appointment,
            'doctors' => $this->doctorOptions()->where('specialty_id', $appointment->specialty_id)->values(),
        ]);
    }

    public function reschedule(Request $request, Appointment $appointment)
    {
        $data = $request->validate([
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
        ], ['time_slot_id.required' => 'Vui lòng chọn khung giờ mới.']);

        $slot = TimeSlot::with('doctor')->findOrFail($data['time_slot_id']);
        if ($slot->doctor->specialty_id !== $appointment->specialty_id) {
            throw new BusinessRuleException('Chỉ đổi sang khung giờ của bác sĩ cùng chuyên khoa.');
        }

        $new = $this->booking->bookByStaff(
            $request->user(),
            $appointment->patient,
            $slot,
            $appointment->symptoms,
            $appointment->source,
            $appointment,
        );

        return redirect()->route('staff.appointments.show', $new)
            ->with('success', 'Đã đổi lịch '.$appointment->code.' sang lịch mới '.$new->code.' lúc '.$new->scheduled_at->format('H:i d/m/Y').'.');
    }

    /** Danh sách bác sĩ đang hoạt động cho bộ chọn (kèm chuyên khoa, phí khám). */
    protected function doctorOptions()
    {
        return Doctor::with(['user', 'specialty', 'room'])->active()->get()
            ->sortBy('name')
            ->map(fn (Doctor $d) => [
                'id' => $d->id,
                'specialty_id' => $d->specialty_id,
                'name' => $d->display_name,
                'specialty' => $d->specialty->name,
                'room' => $d->room?->code,
                'fee' => $d->consultation_fee,
                'avatar' => $d->avatarUrl(),
            ])
            ->values();
    }
}
