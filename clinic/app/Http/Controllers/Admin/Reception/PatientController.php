<?php

namespace App\Http\Controllers\Admin\Reception;

use App\Enums\Gender;
use App\Http\Controllers\Controller;
use App\Models\Clinical\Patient;
use App\Support\Audit;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Tra cứu bệnh nhân (LT-10), cập nhật thông tin hành chính (LT-11). */
class PatientController extends Controller
{
    public const BLOOD_TYPES = ['A', 'B', 'AB', 'O'];

    public function index(Request $request)
    {
        $patients = Patient::with('user')
            ->search($request->input('q'))
            ->latest('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.reception.patients.index', compact('patients'));
    }

    /** JSON cho ô tìm kiếm tự động. */
    public function search(Request $request)
    {
        $q = trim((string) $request->input('q'));
        if (mb_strlen($q) < 2) {
            return response()->json([]);
        }

        $patients = Patient::search($q)->orderBy('full_name')->limit(10)->get()
            ->map(fn (Patient $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'full_name' => $p->full_name,
                'phone' => $p->phone,
                'date_of_birth' => $p->date_of_birth?->format('d/m/Y'),
                'age' => $p->age,
                'gender' => $p->genderLabel(),
                'priority' => $p->isPriority(),
                'summary' => $p->summary(),
            ]);

        return response()->json($patients);
    }

    public function show(Patient $patient)
    {
        $patient->load([
            'user',
            'appointments' => fn ($q) => $q->with(['doctor.user', 'specialty'])->latest('scheduled_at')->limit(20),
            'visits' => fn ($q) => $q->with(['doctor.user', 'specialty', 'diagnoses'])->latest('visit_date')->latest('id')->limit(20),
        ]);

        Audit::viewed($patient, 'Xem hồ sơ bệnh nhân');

        return view('admin.reception.patients.show', compact('patient'));
    }

    public function create()
    {
        return view('admin.reception.patients.form', $this->formData(new Patient));
    }

    public function store(Request $request)
    {
        $patient = Patient::create($this->validated($request));

        return redirect()->route('staff.patients.show', $patient)
            ->with('success', 'Đã thêm bệnh nhân '.$patient->full_name.' - mã '.$patient->code.'.');
    }

    public function edit(Patient $patient)
    {
        return view('admin.reception.patients.form', $this->formData($patient));
    }

    public function update(Request $request, Patient $patient)
    {
        $patient->update($this->validated($request));

        return redirect()->route('staff.patients.show', $patient)->with('success', 'Đã cập nhật thông tin bệnh nhân.');
    }

    protected function formData(Patient $patient): array
    {
        return [
            'patient' => $patient,
            'genders' => Gender::options(),
            'bloodTypes' => self::BLOOD_TYPES,
            'relationships' => Patient::RELATIONSHIPS,
        ];
    }

    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'full_name' => ['required', 'string', 'max:100'],
            'date_of_birth' => ['nullable', 'date', 'before_or_equal:today'],
            'gender' => ['nullable', Rule::enum(Gender::class)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/'],
            'email' => ['nullable', 'email', 'max:191'],
            'id_number' => ['nullable', 'string', 'max:20'],
            'insurance_number' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:255'],
            'occupation' => ['nullable', 'string', 'max:100'],
            'blood_type' => ['nullable', Rule::in(self::BLOOD_TYPES)],
            'allergies' => ['nullable', 'string', 'max:1000'],
            'medical_history' => ['nullable', 'string', 'max:2000'],
            'emergency_contact' => ['nullable', 'string', 'max:255'],
            'relationship' => ['nullable', Rule::in(Patient::RELATIONSHIPS)],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
        ]);

        // Cột relationship không cho phép null: bỏ qua khi không chọn
        if (empty($data['relationship'])) {
            unset($data['relationship']);
        }

        return $data;
    }
}
