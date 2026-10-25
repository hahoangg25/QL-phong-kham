<?php

namespace App\Http\Controllers\Client\Account;

use App\Exceptions\BusinessRuleException;
use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Clinical\Patient;
use App\Models\Scheduling\SlotHold;
use App\Models\Catalog\Specialty;
use App\Models\Scheduling\TimeSlot;
use App\Services\BookingService;
use Illuminate\Http\Request;

/**
 * BN-07, BN-08, BN-09: đặt lịch online gồm 2 bước
 *   1) chọn chuyên khoa - bác sĩ - ngày - khung giờ - hồ sơ -> giữ slot (HT-01) và gửi OTP
 *   2) nhập OTP trong thời gian giữ slot -> tạo lịch hẹn.
 */
class BookingController extends Controller
{
    use AuthorizesOwnership;

    public function __construct(protected BookingService $booking) {}

    public function create(Request $request)
    {
        $user = $request->user();

        $specialties = Specialty::active()->get();
        $doctors = Doctor::with(['user', 'specialty'])->active()->get();

        $doctorId = $request->integer('doctor_id') ?: null;
        $specialtyId = $request->integer('specialty_id') ?: null;
        if ($doctorId && ($doctor = $doctors->firstWhere('id', $doctorId))) {
            $specialtyId = $doctor->specialty_id;
        }

        return view('client.account.booking.create', [
            'specialties' => $specialties,
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
            'patients' => $user->patients()->orderBy('id')->get(),
            'prefill' => [
                'specialty_id' => $specialtyId,
                'doctor_id' => $doctorId,
                'patient_id' => $request->integer('patient_id') ?: null,
            ],
            'rules' => [
                'min_hours' => (int) setting('booking_min_hours_before'),
                'max_days' => (int) setting('booking_max_days_ahead'),
                'hold_minutes' => (int) setting('slot_hold_minutes'),
            ],
            'deposit' => $user->requiredDeposit(),
            'canBook' => $user->canBookOnline(),
            'user' => $user,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'time_slot_id' => ['required', 'integer', 'exists:time_slots,id'],
            'symptoms' => ['nullable', 'string', 'max:1000'],
        ], [
            'time_slot_id.required' => 'Vui lòng chọn khung giờ khám.',
            'patient_id.required' => 'Vui lòng chọn hồ sơ người đi khám.',
        ]);

        $patient = Patient::findOrFail($data['patient_id']);
        $this->authorizePatient($patient);

        $hold = $this->booking->hold(
            $request->user(),
            $patient,
            TimeSlot::findOrFail($data['time_slot_id']),
            $data['symptoms'] ?? null,
        );

        return redirect()->route('account.booking.verify', $hold)
            ->with('success', 'Đã giữ chỗ trong '.setting('slot_hold_minutes').' phút. Mã OTP đã được gửi tới email '.$request->user()->email.'.');
    }

    public function verifyForm(SlotHold $hold)
    {
        $this->authorizeHold($hold);

        if ($hold->isExpired()) {
            $from = $hold->reschedule_from_id;
            $hold->delete();

            return $this->restart($from, 'Đã hết thời gian giữ chỗ, khung giờ đã được giải phóng. Vui lòng chọn lại.');
        }

        $hold->load(['timeSlot.doctor.user', 'timeSlot.doctor.room', 'timeSlot.room', 'specialty', 'patient', 'rescheduleFrom']);

        return view('client.account.booking.verify', ['hold' => $hold]);
    }

    public function verify(Request $request, SlotHold $hold)
    {
        $this->authorizeHold($hold);
        $request->validate(['otp' => ['required', 'digits:6']]);
        $from = $hold->reschedule_from_id;

        try {
            $appointment = $this->booking->confirmHold($hold, $request->input('otp'), $request->user());
        } catch (BusinessRuleException $e) {
            // Chỗ giữ đã bị hủy (hết hạn / nhập sai quá số lần) thì quay lại bước chọn giờ
            if (! SlotHold::whereKey($hold->id)->exists()) {
                return $this->restart($from, $e->getMessage());
            }
            throw $e;
        }

        $messages = [($from ? 'Đổi lịch thành công! Mã lịch hẹn mới: ' : 'Đặt lịch thành công! Mã lịch hẹn: ').$appointment->code.'.'];
        if ($appointment->status->value === 'pending') {
            $messages[] = 'Lịch hẹn đang chờ phòng khám xác nhận, bạn sẽ nhận được thông báo khi lịch được xác nhận.';
        }
        if ($appointment->deposit_required > $appointment->prepaid_amount) {
            $messages[] = 'Tài khoản cần đặt cọc '.money($appointment->deposit_required).' (BR-05). Vui lòng bấm "Thanh toán online" để hoàn tất.';
        }

        return redirect()->route('account.appointments.show', $appointment)->with('success', $messages);
    }

    public function resend(SlotHold $hold)
    {
        $this->authorizeHold($hold);
        $this->booking->resendHoldOtp($hold);

        return back()->with('success', 'Đã gửi lại mã OTP.');
    }

    public function release(SlotHold $hold)
    {
        $this->authorizeHold($hold);
        $from = $hold->reschedule_from_id;
        $hold->delete();

        return $this->restart($from, null, 'Đã hủy giữ chỗ. Bạn có thể chọn khung giờ khác.');
    }

    protected function authorizeHold(SlotHold $hold): void
    {
        abort_unless($hold->user_id === auth()->id(), 403);
    }

    protected function restart(?int $rescheduleFromId, ?string $error = null, ?string $info = null)
    {
        $redirect = $rescheduleFromId
            ? redirect()->route('account.appointments.reschedule', $rescheduleFromId)
            : redirect()->route('account.booking.create');

        return $error ? $redirect->with('error', $error) : $redirect->with('info', $info);
    }
}
