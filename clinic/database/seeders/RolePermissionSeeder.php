<?php

namespace Database\Seeders;

use App\Enums\Role as RoleEnum;
use App\Support\Permissions;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (Permissions::all() as $name) {
            Permission::findOrCreate($name, 'web');
        }

        foreach (RoleEnum::cases() as $role) {
            Role::findOrCreate($role->value, 'web');
        }

        foreach (Permissions::DEFAULT_ROLE_PERMISSIONS as $role => $permissions) {
            Role::findByName($role, 'web')->syncPermissions($permissions);
        }

        // Quản trị viên được Gate::before cho toàn quyền, vẫn gán đủ quyền để hiển thị ma trận
        Role::findByName(RoleEnum::Admin->value, 'web')->syncPermissions(Permissions::all());
    }
}
