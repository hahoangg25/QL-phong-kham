<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

/** QT-01: quản lý tài khoản nhân viên nội bộ. */
class StaffController extends Controller
{
    public function index(Request $request)
    {
        $users = User::staff()
            ->with(['roles', 'doctor'])
            ->when($request->filled('role'), fn ($q) => $q->role($request->role))
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')
                ->orWhere('phone', 'like', $request->q.'%')
                ->orWhere('employee_code', $request->q)))
            ->when($request->filled('status'), fn ($q) => $q->where('is_active', $request->status === 'active'))
            ->orderBy('employee_code')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.management.staff.index', [
            'users' => $users,
            'roles' => Role::staffOptions(),
        ]);
    }

    public function create()
    {
        return view('admin.management.staff.form', ['user' => new User(['is_active' => true]), 'roles' => Role::staffOptions(), 'userRoles' => []]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $user = User::create($data['user'] + [
            'verified_at' => now(),
            'email_verified_at' => now(),
            'consent_at' => now(),
        ]);
        $user->syncRoles($data['roles']);

        $redirect = redirect()->route('staff.admin.staff.index')->with('success', 'Đã tạo tài khoản nhân viên '.$user->name.'.');

        if (in_array(Role::Doctor->value, $data['roles'], true)) {
            return redirect()->route('staff.admin.doctors.create', ['user_id' => $user->id, 'name' => $user->name, 'email' => $user->email, 'phone' => $user->phone])
                ->with('info', 'Tài khoản '.$user->name.' có vai trò Bác sĩ. Vui lòng hoàn thiện hồ sơ chuyên môn (chuyên khoa, phí khám) để bác sĩ nhận lịch hẹn.');
        }

        return $redirect;
    }

    public function edit(User $user)
    {
        abort_unless($user->isStaff(), 404);

        return view('admin.management.staff.form', [
            'user' => $user->load('doctor'),
            'roles' => Role::staffOptions(),
            'userRoles' => $user->getRoleNames()->all(),
        ]);
    }

    public function update(Request $request, User $user)
    {
        abort_unless($user->isStaff(), 404);
        $data = $this->validated($request, $user);

        if (empty($data['user']['password'])) {
            unset($data['user']['password']);
        }

        // Không tự gỡ quyền quản trị / tự khóa chính mình
        if ($user->id === $request->user()->id) {
            if (! in_array(Role::Admin->value, $data['roles'], true)) {
                $data['roles'][] = Role::Admin->value;
            }
            $data['user']['is_active'] = true;
        }

        if ($data['user']['is_active']) {
            $data['user']['locked_reason'] = null;
        }

        $user->update($data['user']);
        $user->syncRoles($data['roles']);

        return redirect()->route('staff.admin.staff.index')->with('success', 'Đã cập nhật tài khoản '.$user->name.'.');
    }

    public function destroy(Request $request, User $user)
    {
        abort_unless($user->isStaff(), 404);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Không thể khóa tài khoản đang đăng nhập.');
        }

        $user->update(['is_active' => false, 'locked_reason' => 'Nghỉ việc']);

        return back()->with('warning', 'Tài khoản '.$user->name.' đã được khóa (dữ liệu lịch sử được giữ nguyên).');
    }

    /** Khóa / mở khóa tài khoản kèm lý do. */
    public function toggle(Request $request, User $user)
    {
        abort_unless($user->isStaff(), 404);

        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Bạn không thể khóa tài khoản của chính mình.');
        }

        if ($user->is_active) {
            $reason = $request->validate(['reason' => ['nullable', 'string', 'max:191']])['reason'] ?? null;
            $user->update(['is_active' => false, 'locked_reason' => $reason ?: 'Khóa bởi quản trị viên']);

            return back()->with('success', 'Đã khóa tài khoản '.$user->name.'.');
        }

        $user->update(['is_active' => true, 'locked_reason' => null]);

        return back()->with('success', 'Đã mở khóa tài khoản '.$user->name.'.');
    }

    protected function validated(Request $request, ?User $user = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'email' => ['required', 'email', 'max:191', Rule::unique('users', 'email')->ignore($user?->id)],
            'phone' => ['nullable', 'regex:/^0\d{9}$/', Rule::unique('users', 'phone')->ignore($user?->id)],
            'employee_code' => ['nullable', 'string', 'max:20', Rule::unique('users', 'employee_code')->ignore($user?->id)],
            'password' => [$user ? 'nullable' : 'required', 'confirmed', Password::min(8)],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => [Rule::in(Role::staffValues())],
        ], [
            'phone.regex' => 'Số điện thoại phải gồm 10 chữ số, bắt đầu bằng 0.',
            'roles.required' => 'Vui lòng chọn ít nhất một vai trò.',
        ]);

        return [
            'user' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'employee_code' => $data['employee_code'] ?? null,
                'password' => $data['password'] ?? null,
                'is_active' => $request->boolean('is_active'),
            ],
            'roles' => array_values(array_unique($data['roles'])),
        ];
    }
}
