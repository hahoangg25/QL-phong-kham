<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\Catalog\Doctor;
use App\Models\Catalog\Room;
use App\Models\Catalog\Specialty;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** QT-05: quản lý bác sĩ (tài khoản nhân viên + hồ sơ chuyên môn). */
class DoctorController extends Controller
{
    public const TITLES = ['BS', 'BSCKI', 'BSCKII', 'ThS.BS', 'TS.BS', 'PGS.TS.BS', 'GS.TS.BS'];

    public function index(Request $request)
    {
        $doctors = Doctor::with(['user', 'specialty', 'room'])
            ->when($request->filled('q'), fn ($q) => $q->whereHas('user', fn ($u) => $u->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')->orWhere('phone', 'like', $request->q.'%')))
            ->when($request->filled('specialty_id'), fn ($q) => $q->where('specialty_id', $request->specialty_id))
            ->when($request->filled('status'), fn ($q) => $q->where('doctors.is_active', $request->status === 'active'))
            ->orderBy('specialty_id')->orderBy('id')
            ->paginate(20)->withQueryString();

        return view('admin.management.doctors.index', [
            'doctors' => $doctors,
            'specialties' => Specialty::orderBy('sort_order')->get(),
        ]);
    }

    public function create(Request $request)
    {
        $doctor = new Doctor(['is_active' => true, 'consultation_fee' => 200000]);
        $doctor->setRelation('user', new User($request->only('name', 'email', 'phone')));

        return view('admin.management.doctors.form', $this->formData($doctor) + ['linkUserId' => $request->integer('user_id') ?: null]);
    }

    public function store(Request $request)
    {
        $linkUser = $request->filled('link_user_id') ? User::find($request->integer('link_user_id')) : null;
        if ($linkUser && $linkUser->doctor) {
            $linkUser = null;
        }

        $data = $this->validated($request, null, $linkUser);

        $doctor = DB::transaction(function () use ($data, $request, $linkUser) {
            if ($linkUser) {
                $user = $linkUser;
                $user->update(collect($data['user'])->except('password')->filter(fn ($v) => $v !== null)->all()
                    + (! empty($data['user']['password']) ? ['password' => $data['user']['password']] : []));
            } else {
                $user = User::create($data['user'] + [
                    'verified_at' => now(),
                    'email_verified_at' => now(),
                    'consent_at' => now(),
                ]);
            }
            if (! $user->hasRole(Role::Doctor->value)) {
                $user->assignRole(Role::Doctor->value);
            }

            return Doctor::create($data['doctor'] + [
                'user_id' => $user->id,
                'avatar' => $this->upload($request),
            ]);
        });

        return redirect()->route('staff.admin.doctors.index')->with('success', 'Đã thêm bác sĩ '.$doctor->display_name.'.');
    }

    public function edit(Doctor $doctor)
    {
        $doctor->load('user');

        return view('admin.management.doctors.form', $this->formData($doctor));
    }

    public function update(Request $request, Doctor $doctor)
    {
        $data = $this->validated($request, $doctor);

        DB::transaction(function () use ($data, $request, $doctor) {
            $userData = $data['user'];
            if (empty($userData['password'])) {
                unset($userData['password']);
            }
            // Kích hoạt lại bác sĩ thì mở khóa luôn tài khoản đăng nhập
            if ($data['doctor']['is_active'] && ! $doctor->user->is_active) {
                $userData += ['is_active' => true, 'locked_reason' => null];
            }
            $doctor->user->update($userData);

            if ($path = $this->upload($request)) {
                $data['doctor']['avatar'] = $path;
            }
            $doctor->update($data['doctor']);
        });

        return redirect()->route('staff.admin.doctors.index')->with('success', 'Đã cập nhật hồ sơ bác sĩ '.$doctor->fresh('user')->display_name.'.');
    }

    public function destroy(Doctor $doctor)
    {
        DB::transaction(function () use ($doctor) {
            $doctor->update(['is_active' => false]);
            $doctor->user->update(['is_active' => false, 'locked_reason' => 'Bác sĩ ngừng công tác']);
        });

        return back()->with('warning', 'Bác sĩ '.$doctor->display_name.' đã được chuyển sang ngừng hoạt động và khóa tài khoản (dữ liệu khám được giữ nguyên).');
    }

    protected function formData(Doctor $doctor): array
    {
        return [
            'doctor' => $doctor,
            'specialties' => Specialty::orderBy('sort_order')->get(),
            'rooms' => Room::orderBy('code')->get(),
            'titles' => self::TITLES,
        ];
    }

    protected function validated(Request $request, ?Doctor $doctor = null, ?User $linkUser = null): array
    {
        $userId = $doctor?->user_id ?? $linkUser?->id;

        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($userId)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/', Rule::unique('users', 'phone')->ignore($userId)],
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('users', 'employee_code')->ignore($userId)],
            'password' => [$doctor || $linkUser ? 'nullable' : 'required', Password::min(8)],
            'specialty_id' => ['required', 'exists:specialties,id'],
            'room_id' => ['nullable', 'exists:rooms,id'],
            'title' => ['nullable', 'string', 'max:50'],
            'position' => ['nullable', 'string', 'max:191'],
            'experience_years' => ['nullable', 'integer', 'min:0', 'max:80'],
            'consultation_fee' => ['required', 'integer', 'min:0', 'max:100000000'],
            'avatar_file' => ['nullable', 'image', 'max:2048'],
            'short_bio' => ['nullable', 'string', 'max:500'],
            'bio' => ['nullable', 'string'],
        ], ['phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.']);

        return [
            'user' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'employee_code' => $data['employee_code'] ?? null,
                'password' => $data['password'] ?? null,
            ],
            'doctor' => [
                'specialty_id' => $data['specialty_id'],
                'room_id' => $data['room_id'] ?? null,
                'title' => $data['title'] ?? null,
                'position' => $data['position'] ?? null,
                'experience_years' => (int) ($data['experience_years'] ?? 0),
                'consultation_fee' => (int) $data['consultation_fee'],
                'short_bio' => $data['short_bio'] ?? null,
                'bio' => $data['bio'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
        ];
    }

    protected function upload(Request $request): ?string
    {
        if (! $request->hasFile('avatar_file')) {
            return null;
        }

        $file = $request->file('avatar_file');
        $name = Str::random(20).'.'.$file->getClientOriginalExtension();
        $file->move(public_path('uploads/doctors'), $name);

        return 'uploads/doctors/'.$name;
    }
}
