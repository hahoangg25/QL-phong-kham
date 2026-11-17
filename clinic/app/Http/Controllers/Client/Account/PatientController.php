<?php

namespace App\Http\Controllers\Client\Account;

use App\Enums\Gender;
use App\Http\Controllers\Client\Account\Concerns\AuthorizesOwnership;
use App\Http\Controllers\Controller;
use App\Models\Clinical\Patient;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** BN-04: quản lý nhiều hồ sơ bệnh nhân (bản thân, người thân) trong một tài khoản. */
class PatientController extends Controller
{
    use AuthorizesOwnership;

    public function index(Request $request)
    {
        $patients = $request->user()->patients()
            ->withCount(['appointments as active_appointments_count' => fn ($q) => $q->active()])
            ->withCount('visits')
            ->orderBy('id')
            ->get()
            ->sortBy(fn (Patient $p) => $p->relationship === 'Bản thân' ? 0 : 1)
            ->values();

        return view('client.account.patients.index', compact('patients'));
    }

    public function create()
    {
        return view('client.account.patients.form', $this->formData(new Patient(['relationship' => 'Con'])));
    }

    public function store(Request $request)
    {
        $patient = $request->user()->patients()->create($this->validated($request));

        $redirect = $request->input('redirect_to') === 'booking'
            ? route('account.booking.create', ['patient_id' => $patient->id])
            : route('account.patients.index');

        return redirect($redirect)->with('success', 'Đã thêm hồ sơ '.$patient->full_name.' (mã '.$patient->code.').');
    }

    public function edit(Patient $patient)
    {
        $this->authorizePatient($patient);

        return view('client.account.patients.form', $this->formData($patient));
    }

    public function update(Request $request, Patient $patient)
    {
        $this->authorizePatient($patient);
        $patient->update($this->validated($request));

        return redirect()->route('account.patients.index')->with('success', 'Đã cập nhật hồ sơ '.$patient->full_name.'.');
    }

    public function destroy(Patient $patient)
    {
        $this->authorizePatient($patient);

        if ($patient->relationship === 'Bản thân') {
            return back()->with('error', 'Không thể xóa hồ sơ của chính chủ tài khoản.');
        }

        if ($patient->appointments()->active()->exists()) {
            return back()->with('error', 'Hồ sơ '.$patient->full_name.' đang có lịch hẹn chưa hoàn tất, không thể xóa.');
        }

        $patient->delete();

        return back()->with('success', 'Đã xóa hồ sơ '.$patient->full_name.'.');
    }

    protected function validated(Request $request): array
    {
        return $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'relationship' => ['required', Rule::in(Patient::RELATIONSHIPS)],
            'date_of_birth' => ['required', 'date', 'before_or_equal:today'],
            'gender' => ['required', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'id_number' => ['nullable', 'regex:/^\d{9,12}$/'],
            'insurance_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'blood_type' => ['nullable', Rule::in(['A', 'B', 'AB', 'O'])],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_history' => ['nullable', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
            'id_number.regex' => 'Số CCCD/CMND gồm 9 hoặc 12 chữ số.',
        ]);
    }

    protected function formData(Patient $patient): array
    {
        return [
            'patient' => $patient,
            'relationships' => Patient::RELATIONSHIPS,
            'genders' => Gender::options(),
        ];
    }
}
