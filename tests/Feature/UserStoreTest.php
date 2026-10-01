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
        $extra = Office::create(['code' => '12000', 'name' => 'SIDA-HRD']);

        $this->actingAs($admin)->get(route('core.users.entry'))->assertOk()->assertSee('05001 · PPSPD-PS — Planning Section', false);

        $this->postJson(route('core.users.store'), [
            'fname' => 'Juan', 'lname' => 'Cruz', 'username' => 'jcruz', 'email' => 'jcruz@example.com',
            'password' => 'secret', 'password_confirmation' => 'secret', 'categories' => 1,
            'role' => 'User', 'office_id' => $office->id, 'designation' => 'Senior Agriculturist',
            'office_ids' => [$office->id, $extra->id],
        ])->assertOk()->assertJson(['status' => 'success']);

        $user = User::where('username', 'jcruz')->sole();
        $this->assertSame($office->id, $user->office_id);
        $this->assertSame('Senior Agriculturist', $user->designation);
        $this->assertTrue($user->hasRole('User'));
        // Home office is not duplicated as an extra office
        $this->assertSame([$extra->id], $user->offices()->pluck('offices.id')->all());
    }

    public function test_fullname_skips_missing_middle_initial(): void
    {
        $with = User::factory()->create(['fname' => 'Juan', 'minitial' => 'M', 'lname' => 'Cruz'])->fresh();
        $without = User::factory()->create(['fname' => 'Maria', 'minitial' => null, 'lname' => 'Santos'])->fresh();
        $blank = User::factory()->create(['fname' => 'Jose', 'minitial' => '', 'lname' => 'Rizal'])->fresh();

        $this->assertSame('Juan M. Cruz', $with->fullname);
        $this->assertSame('Maria Santos', $without->fullname);
        $this->assertSame('Jose Rizal', $blank->fullname);
    }
}
