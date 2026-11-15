<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Http\Request;

/** QT-12: quản lý tài khoản bệnh nhân - khóa tài khoản vi phạm, gỡ hạn chế no-show. */
class PatientAccountController extends Controller
{
    public function index(Request $request)
    {
        $threshold = (int) setting('no_show_threshold');

        $users = User::role(Role::Patient->value)
            ->withCount('patients')
            ->when($request->filled('q'), fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.$request->q.'%')
                ->orWhere('email', 'like', '%'.$request->q.'%')
                ->orWhere('phone', 'like', $request->q.'%')))
            ->when($request->input('filter') === 'restricted', fn ($q) => $q->where('no_show_count', '>=', $threshold))
            ->when($request->input('filter') === 'locked', fn ($q) => $q->where('is_active', false))
            ->when($request->input('filter') === 'unverified', fn ($q) => $q->whereNull('verified_at'))
            ->orderByDesc('no_show_count')->orderBy('name')
            ->paginate(20)->withQueryString();

        return view('admin.management.patient-accounts.index', [
            'users' => $users,
            'threshold' => $threshold,
            'policy' => setting('no_show_policy'),
            'restrictedCount' => User::role(Role::Patient->value)->where('no_show_count', '>=', $threshold)->count(),
        ]);
    }

    public function toggle(Request $request, User $user)
    {
        abort_unless($user->isPatient(), 404);

        if ($user->is_active) {
            $reason = $request->validate(['reason' => ['nullable', 'string', 'max:191']])['reason'] ?? null;
            $user->update(['is_active' => false, 'locked_reason' => $reason ?: 'Vi phạm quy định sử dụng']);

            return back()->with('success', 'Đã khóa tài khoản '.$user->name.'.');
        }

        $user->update(['is_active' => true, 'locked_reason' => null]);

        return back()->with('success', 'Đã mở khóa tài khoản '.$user->name.'.');
    }

    /** Gỡ hạn chế đặt lịch do vắng mặt nhiều lần (BR-05). */
    public function resetNoShow(User $user)
    {
        abort_unless($user->isPatient(), 404);

        $old = $user->no_show_count;
        $user->update(['no_show_count' => 0]);
        Audit::log('account', 'Gỡ hạn chế no-show cho tài khoản bệnh nhân', $user, ['old_no_show_count' => $old]);

        return back()->with('success', 'Đã gỡ hạn chế đặt lịch cho '.$user->name.' (số lần vắng mặt: '.$old.' → 0).');
    }
}
