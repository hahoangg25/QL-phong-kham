<?php

namespace App\Http\Controllers\Admin\Reception;

use App\Enums\AppointmentStatus;
use App\Enums\Gender;
use App\Enums\VisitStatus;
use App\Http\Controllers\Controller;
use App\Models\Scheduling\Appointment;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\TimeSlot;
use App\Models\Clinical\Visit;
use App\Services\QueueService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Tiếp đón: check-in bằng mã/QR/SĐT (LT-03), tiếp nhận bệnh nhân vãng lai (LT-04). */
class ReceptionController extends Controller
{
    public function __construct(protected QueueService $queue) {}

    public function checkinForm(Request $request)
    {
        $q = trim((string) $request->query('q'));
        $results = null;

        if ($q !== '') {
            $results = Appointment::with(['patient', 'doctor.user', 'doctor.room', 'specialty', 'visit'])
                ->where('code', mb_strtoupper($q))
                ->get();

            if ($results->isEmpty()) {
                // Tra theo SĐT: lịch hẹn hôm nay của các hồ sơ có số điện thoại này (hoặc thuộc tài khoản có SĐT này)
                $results = Appointment::with(['patient', 'doctor.user', 'doctor.room', 'specialty', 'visit'])
                    ->whereDate('appointment_date', today())
                    ->whereHas('patient', fn ($p) => $p->where('phone', $q)->orWhereHas('user', fn ($u) => $u->where('phone', $q)))
                    ->orderBy('scheduled_at')
                    ->get();
            }
        }

        $todayList = Appointment::with(['patient', 'doctor.user', 'specialty'])
            ->whereDate('appointment_date', today())
            ->whereIn('status', [AppointmentStatus::Pending->value, AppointmentStatus::Confirmed->value])
            ->orderBy('scheduled_at')
            ->get();

        return view('admin.reception.checkin', [
            'q' => $q,
            'results' => $results,
            'todayList' => $todayList,
            'lateMinutes' => (int) setting('late_checkin_minutes'),
        ]);
    }

    public function lookup(Request $request)
    {
        $data = $request->validate(['q' => ['required', 'string', 'max:50']], [
            'q.required' => 'Vui lòng nhập mã lịch hẹn hoặc số điện thoại.',
        ]);

        return redirect()->route('staff.reception.checkin', ['q' => trim($data['q'])]);
    }

    public function checkin(Request $request, Appointment $appointment)
    {
        $visit = $this->queue->checkIn($appointment, $request->user());
        $visit->load(['doctor.user', 'room', 'doctor.room']);

        return back()
            ->with('success', 'Check-in thành công: '.$appointment->patient->full_name.' - STT '.$visit->queue_number
                .' - '.$visit->doctor->display_name.' (phòng '.(($visit->room ?? $visit->doctor->room)?->code ?? '-').').')
            ->with('ticket_url', route('staff.queue.ticket', $visit));
    }

    public function walkInForm(Request $request)
    {
        $workingToday = TimeSlot::whereDate('date', today())->distinct()->pluck('doctor_id')->all();
        $waiting = Visit::today()->where('status', VisitStatus::Waiting->value)
            ->selectRaw('doctor_id, COUNT(*) as c')->groupBy('doctor_id')->pluck('c', 'doctor_id');

        $doctors = Doctor::with(['user', 'specialty', 'room'])->active()->get()
            ->map(fn (Doctor $d) => [
                'id' => $d->id,
                'name' => $d->display_name,
                'specialty' => $d->specialty->name,
                'room' => $d->room?->code,
                'working' => in_array($d->id, $workingToday, true),
                'waiting' => (int) ($waiting[$d->id] ?? 0),
            ])
            ->sortBy([['working', 'desc'], ['specialty', 'asc'], ['name', 'asc']])
            ->values();

        return view('admin.reception.walkin', [
            'doctors' => $doctors,
            'genders' => Gender::options(),
            'patient' => $request->filled('patient_id') ? Patient::find($request->integer('patient_id')) : null,
            'priorityAges' => [(int) setting('priority_age_elderly'), (int) setting('priority_age_child')],
        ]);
    }

    public function walkIn(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['nullable', 'integer', 'exists:patients,id'],
            'full_name' => ['required_without:patient_id', 'nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'id_number' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string', 'max:255'],
            'doctor_id' => ['required', 'integer', 'exists:doctors,id'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], [
            'full_name.required_without' => 'Vui lòng chọn bệnh nhân có sẵn hoặc nhập họ tên bệnh nhân mới.',
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
            'doctor_id.required' => 'Vui lòng chọn bác sĩ khám.',
        ]);

        $visit = DB::transaction(function () use ($data, $request) {
            $patient = ! empty($data['patient_id'])
                ? Patient::findOrFail($data['patient_id'])
                : Patient::create(collect($data)->only(['full_name', 'phone', 'date_of_birth', 'gender', 'id_number', 'address'])->all());

            return $this->queue->walkIn(
                $patient,
                Doctor::findOrFail($data['doctor_id']),
                $request->user(),
                $data['reason'] ?? null,
                $request->boolean('priority'),
            );
        });

        $visit->load(['patient', 'doctor.user']);

        return redirect()->route('staff.reception.walkin')
            ->with('success', 'Đã tiếp nhận '.$visit->patient->full_name.' (mã '.$visit->patient->code.') - STT '.$visit->queue_number.' - '.$visit->doctor->display_name.'.')
            ->with('ticket_url', route('staff.queue.ticket', $visit));
    }
}
