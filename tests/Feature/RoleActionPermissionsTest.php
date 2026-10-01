<?php

namespace Tests\Feature;

use App\Core\Models\Menu;
use App\Models\User;
use Database\Seeders\ActionPermissionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class RoleActionPermissionsTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, MenuSeeder::class, ActionPermissionSeeder::class]);
        $this->admin = User::factory()->create(['is_activated' => 1]);
        $this->admin->assignRole('Super Admin');
    }

    public function test_role_screen_lists_and_grants_hidden_action_gates(): void
    {
        $bac = Role::create(['name' => 'BAC', 'guard_name' => 'web']);
        $ppmp = Menu::where('name', 'PPMP')->first();
        $viewAll = Menu::where('name', 'PPMP View All')->first();

        $this->actingAs($this->admin)->get(route('core.roles.permissions', $bac))
            ->assertOk()->assertSee('View All')->assertSee('menu_ids-' . $viewAll->id, false);

        $this->put(route('core.roles.permissions.update', $bac), ['menu_ids' => [$ppmp->id, $viewAll->id]])
            ->assertRedirect();

        $bac->refresh();
        $this->assertTrue($bac->hasPermissionTo('menu.ppmp'));
        $this->assertTrue($bac->hasPermissionTo('manage ppmp'));
        $this->assertTrue($bac->hasPermissionTo('menu.ppmp-view-all'));
    }

    public function test_saving_admin_role_keeps_its_delete_gates(): void
    {
        $admin = Role::where('name', 'Admin')->first();
        $this->assertTrue($admin->hasPermissionTo('menu.manage-users-destroy'));

        // Re-submit exactly what the screen shows as checked
        $html = $this->actingAs($this->admin)->get(route('core.roles.permissions', $admin))->getContent();
        preg_match_all('/name="menu_ids\[\]"[^>]*value="(\d+)"[^>]*checked/', $html, $m);

        $this->put(route('core.roles.permissions.update', $admin), ['menu_ids' => $m[1]]);

        $this->assertTrue($admin->fresh()->hasPermissionTo('menu.manage-users-destroy'));
    }

    public function test_user_access_can_grant_a_hidden_gate_to_one_user(): void
    {
        $user = User::factory()->create(['is_activated' => 1]);
        $permissionId = \Spatie\Permission\Models\Permission::where('name', 'menu.ppmp-view-all')->value('id');

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $user->id]))
            ->assertOk()->assertSee('View All');

        $this->put(route('core.access.update', $user), [
            'perm_override' => [$permissionId => 1],
            'perm_state'    => [$permissionId => 1],
        ])->assertRedirect();

        $this->assertTrue($user->fresh()->canAccessPermission('menu.ppmp-view-all'));
    }
}
