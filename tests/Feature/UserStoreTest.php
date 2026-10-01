<?php

namespace Tests\Feature;

use App\Models\Procurement\Office;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class UserStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_user_with_office_designation_and_role(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_activated' => 1]);
        $admin->assignRole('Super Admin');
        $office = Office::create(['code' => '05001', 'acronym' => 'PPSPD-PS', 'name' => 'Planning Section']);

        $this->actingAs($admin)->get(route('core.users.entry'))->assertOk()->assertSee('05001 · PPSPD-PS — Planning Section', false);

        $this->postJson(route('core.users.store'), [
            'fname' => 'Juan', 'lname' => 'Cruz', 'username' => 'jcruz', 'email' => 'jcruz@example.com',
            'password' => 'secret', 'password_confirmation' => 'secret', 'categories' => 1,
            'role' => 'User', 'office_id' => $office->id, 'designation' => 'Senior Agriculturist',
        ])->assertOk()->assertJson(['status' => 'success']);

        $user = User::where('username', 'jcruz')->sole();
        $this->assertSame($office->id, $user->office_id);
        $this->assertSame('Senior Agriculturist', $user->designation);
        $this->assertTrue($user->hasRole('User'));
    }
}
