<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $user = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'fname' => 'Super',
                'lname' => 'Admin',
                'username' => 'superadmin',
                'password' => bcrypt('password'),
                'categories' => 1,
                'is_activated' => true,
                'img_slug' => 'avatar-default.png',
            ]
        );

        $superAdmin = Role::where('name', 'Super Admin')->first();

        if ($superAdmin && ! $user->hasRole('Super Admin')) {
            $user->assignRole($superAdmin);
        }

        $this->command?->info('Default Super Admin ready — email: admin@example.com / password: password');
    }
}