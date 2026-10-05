<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $systemPermissions = [
            'manage roles',
            'manage menus',
            'manage users',
            'manage access',
            'manage settings',
        ];

        foreach ($systemPermissions as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        // Procurement gates; granted to roles in Roles & Permissions, not to Admin by default
        foreach (['manage offices', 'manage ppmp', 'manage app', 'manage lookups'] as $perm) {
            Permission::firstOrCreate(['name' => $perm, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Super Admin', 'guard_name' => 'web']);
        $admin = Role::firstOrCreate(['name' => 'Admin', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'User', 'guard_name' => 'web']);

        $admin->givePermissionTo($systemPermissions);
    }
}