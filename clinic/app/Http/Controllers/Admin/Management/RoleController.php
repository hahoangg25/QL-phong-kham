<?php

namespace App\Http\Controllers\Admin\Management;

use App\Enums\Role as RoleEnum;
use App\Http\Controllers\Controller;
use App\Support\Audit;
use App\Support\Permissions;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

/** QT-02: ma trận phân quyền vai trò × quyền (RBAC). */
class RoleController extends Controller
{
    public function index()
    {
        $roles = Role::with('permissions')->whereIn('name', RoleEnum::staffValues())->get()->keyBy('name');

        $matrix = [];
        foreach (RoleEnum::staffValues() as $role) {
            $matrix[$role] = $roles->get($role)?->permissions->pluck('name')->all() ?? [];
        }

        $userCounts = [];
        foreach (RoleEnum::staffValues() as $role) {
            $userCounts[$role] = $roles->get($role)?->users()->count() ?? 0;
        }

        return view('admin.management.roles.index', [
            'roleLabels' => RoleEnum::staffOptions(),
            'groups' => Permissions::GROUPS,
            'matrix' => $matrix,
            'userCounts' => $userCounts,
        ]);
    }

    public function update(Request $request)
    {
        $valid = Permissions::all();
        $input = $request->input('permissions', []);

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        $changes = [];
        foreach (RoleEnum::staffValues() as $roleName) {
            if ($roleName === RoleEnum::Admin->value) {
                continue; // Quản trị viên luôn có toàn quyền
            }
            $role = Role::findOrCreate($roleName, 'web');
            $permissions = array_values(array_intersect($valid, (array) ($input[$roleName] ?? [])));
            $before = $role->permissions->pluck('name')->sort()->values()->all();
            $role->syncPermissions($permissions);
            sort($permissions);
            if ($before !== $permissions) {
                $changes[$roleName] = ['old' => $before, 'new' => $permissions];
            }
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        if ($changes) {
            Audit::log('account', 'Cập nhật ma trận phân quyền', null, $changes);
        }

        return back()->with('success', $changes
            ? 'Đã cập nhật phân quyền cho '.count($changes).' vai trò.'
            : 'Không có thay đổi nào.');
    }
}
