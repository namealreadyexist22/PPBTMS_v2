<?php

namespace Tests\Feature;

use App\Core\Models\Menu;
use App\Core\Services\MenuService;
use App\Models\User;
use Database\Seeders\ActionPermissionSeeder;
use Database\Seeders\MenuSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserAccessTest extends TestCase
{
    use RefreshDatabase;

    protected User $admin;
    protected User $staff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, MenuSeeder::class, ActionPermissionSeeder::class]);

        $this->admin = User::factory()->create(['is_activated' => 1]);
        $this->admin->assignRole('Super Admin');

        // Like the demo users: permissions given directly, no role
        $this->staff = User::factory()->create(['is_activated' => 1]);
        $this->staff->givePermissionTo(['manage ppmp', 'menu.ppmp', 'menu.division-ppmp', 'manage requests', 'menu.purchase-requests']);
    }

    protected function menu(string $name): Menu
    {
        return Menu::where('name', $name)->firstOrFail();
    }

    /** Names of every link in the user's sidebar, groups included. */
    protected function sidebar(User $user): Collection
    {
        $names = function (Collection $items) use (&$names) {
            return $items->flatMap(fn (Menu $m) => collect([$m->name])->merge($names($m->children)));
        };

        return $names(app(MenuService::class)->getMenuTreeForUser($user->fresh()));
    }

    protected function save(User $user, array $data)
    {
        return $this->actingAs($this->admin)->put(route('core.access.update', $user), $data)->assertRedirect();
    }

    public function test_a_page_given_directly_can_be_denied_and_set_back_to_default(): void
    {
        $ppmp = $this->menu('PPMP');

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $this->staff->id]))->assertOk()
            ->assertSee('Default: allowed (given directly)')
            ->assertSee('name="menu_access[' . $ppmp->id . ']" id="access-menu_access-' . $ppmp->id . '-inherit" value="inherit" autocomplete="off" checked', false);

        $this->save($this->staff, ['menu_access' => [$ppmp->id => 'deny']]);

        $this->assertNotContains('PPMP', $this->sidebar($this->staff));
        $this->assertFalse($this->staff->fresh()->canAccessPermission('manage ppmp'));
        $this->actingAs($this->staff->fresh())->get(route('procurement.ppmp.index'))->assertForbidden();

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $this->staff->id]))
            ->assertSee('value="deny" autocomplete="off" checked', false)->assertSee('1 override', false);

        $this->save($this->staff, ['menu_access' => [$ppmp->id => 'inherit']]);

        $this->assertContains('PPMP', $this->sidebar($this->staff));
        $this->assertTrue($this->staff->fresh()->canAccessPermission('manage ppmp'));
        $this->assertDatabaseCount('menu_user_overrides', 0);
        $this->assertDatabaseCount('permission_user_overrides', 0);
    }

    public function test_allowing_a_page_opens_its_link_and_its_routes(): void
    {
        $budget = $this->menu('Budget Allocation');
        $this->assertNotContains('Budget Allocation', $this->sidebar($this->staff));

        $this->save($this->staff, ['menu_access' => [$budget->id => 'allow']]);

        $this->assertContains('Budget Allocation', $this->sidebar($this->staff));
        $this->actingAs($this->staff->fresh())->get(route('procurement.budget.index'))->assertOk();
    }

    public function test_activity_logs_follow_their_page(): void
    {
        $logs = $this->menu('Activity Logs');
        $this->actingAs($this->staff)->get(route('core.logs.index'))->assertForbidden();

        $this->save($this->staff, ['menu_access' => [$logs->id => 'allow']]);

        $this->assertContains('Activity Logs', $this->sidebar($this->staff));
        $this->actingAs($this->staff->fresh())->get(route('core.logs.index'))->assertOk()->assertDontSee('Clear Log');

        // A role that has the page opens it too (there is no "manage logs" permission)
        $auditor = User::factory()->create(['is_activated' => 1]);
        $auditor->assignRole(Role::create(['name' => 'Auditor', 'guard_name' => 'web'])->givePermissionTo('menu.activity-logs'));
        $this->actingAs($auditor)->get(route('core.logs.index'))->assertOk();
    }

    public function test_division_ppmp_page_follows_its_own_override(): void
    {
        $division = $this->menu('Division PPMP');
        $this->actingAs($this->staff)->get(route('procurement.division-ppmp.index'))->assertOk();

        $this->save($this->staff, ['menu_access' => [$division->id => 'deny']]);

        $this->assertNotContains('Division PPMP', $this->sidebar($this->staff));
        $this->actingAs($this->staff->fresh())->get(route('procurement.division-ppmp.index'))->assertForbidden();
        $this->get(route('procurement.ppmp.index'))->assertOk();   // the PPMP page itself is untouched

        // Allowed to someone without PPMP: the link and the page both open
        $head = User::factory()->create(['is_activated' => 1]);
        $this->save($head, ['menu_access' => [$division->id => 'allow']]);
        $this->assertContains('Division PPMP', $this->sidebar($head));
        $this->actingAs($head->fresh())->get(route('procurement.division-ppmp.index'))->assertOk();
    }

    public function test_a_group_hides_when_every_page_under_it_is_denied(): void
    {
        $this->assertContains('Planning', $this->sidebar($this->staff));

        $this->save($this->staff, ['menu_access' => collect(['PPMP', 'Division PPMP'])
            ->mapWithKeys(fn ($name) => [$this->menu($name)->id => 'deny'])->all()]);

        $this->assertNotContains('Planning', $this->sidebar($this->staff));
        $this->assertContains('Purchase Requests', $this->sidebar($this->staff));   // its own link, not under a group
    }

    public function test_procurement_pages_are_grouped_and_moving_them_keeps_their_access(): void
    {
        $tree = app(MenuService::class)->getMenuTreeForUser($this->admin->fresh());
        $children = fn (string $group) => $tree->firstWhere('name', $group)->children->pluck('name')->all();

        $this->assertSame(['Dashboard', 'Purchase Requests'], $tree->take(2)->pluck('name')->all());
        $this->assertSame(['Budget Allocation', 'PPMP', 'Item Requests', 'Division PPMP', 'APP'], $children('Planning'));
        $this->assertSame(['Requests for Quotation', 'Abstract of Quotations', 'TWG Evaluation', 'Suppliers', 'Standard Items'], $children('Procurement'));
        $this->assertSame(['Signatories & BAC', 'Procurement Lookups'], $children('Procurement Setup'));
        $this->assertNotContains('Procurement Lookups', $children('Settings'));
        $this->assertSame(1, Menu::where('name', 'Procurement')->count());

        // Seeding again (as on an existing install) moves nothing twice and keeps each page's id
        $ppmpId = $this->menu('PPMP')->id;
        $this->seed(MenuSeeder::class);
        $this->assertSame($ppmpId, $this->menu('PPMP')->id);
        $this->assertSame(1, Menu::where('name', 'PPMP')->count());
    }

    public function test_hidden_action_gates_can_be_denied(): void
    {
        $viewAll = Permission::where('name', 'menu.ppmp-view-all')->value('id');
        $bac = Role::create(['name' => 'BAC', 'guard_name' => 'web'])->givePermissionTo(['manage ppmp', 'menu.ppmp', 'menu.ppmp-view-all']);
        $this->staff->assignRole($bac);

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $this->staff->id]))->assertSee('Default: allowed (role)');

        $this->save($this->staff, ['perm_access' => [$viewAll => 'deny']]);

        $this->assertFalse($this->staff->fresh()->canAccessPermission('menu.ppmp-view-all'));
        $this->assertTrue($this->staff->fresh()->canAccessPermission('manage ppmp'));
    }

    public function test_a_super_admin_can_be_denied_a_page(): void
    {
        $other = User::factory()->create(['is_activated' => 1]);
        $other->assignRole('Super Admin');
        $users = $this->menu('Manage Users');

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $other->id]))->assertSee('Default: allowed (Super Admin)');
        $this->save($other, ['menu_access' => [$users->id => 'deny']]);

        $this->assertNotContains('Manage Users', $this->sidebar($other));
        $this->actingAs($other->fresh())->get(route('core.users.index'))->assertForbidden();
    }

    public function test_a_page_row_shows_what_guards_its_pages(): void
    {
        $pagesOnly = User::factory()->create(['is_activated' => 1]);
        $pagesOnly->assignRole(Role::create(['name' => 'Pages only', 'guard_name' => 'web'])->givePermissionTo('manage users'));
        $linkOnly = User::factory()->create(['is_activated' => 1]);
        $linkOnly->assignRole(Role::create(['name' => 'Link only', 'guard_name' => 'web'])->givePermissionTo('menu.manage-users'));

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $pagesOnly->id]))
            ->assertSee('Default: pages allowed (role), no sidebar link');
        $this->get(route('core.access.index', ['user' => $linkOnly->id]))
            ->assertSee('Default: sidebar link only (role), pages blocked');

        // The seeded Admin role has both, like a role saved on the Roles screen
        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo('menu.manage-users'));
        $this->assertTrue(Role::findByName('Admin')->hasPermissionTo('manage users'));
    }

    public function test_a_manage_override_without_its_page_override_shows_and_clears(): void
    {
        // Saved before "manage ppmp" was linked to the PPMP page
        $manage = Permission::where('name', 'manage ppmp')->first();
        \App\Core\Models\PermissionUserOverride::create(['user_id' => $this->staff->id, 'permission_id' => $manage->id, 'access' => 'deny']);
        $ppmp = $this->menu('PPMP');

        $this->actingAs($this->admin)->get(route('core.access.index', ['user' => $this->staff->id]))
            ->assertSee('id="access-menu_access-' . $ppmp->id . '-deny" value="deny" autocomplete="off" checked', false)
            ->assertSee('1 override');

        $this->save($this->staff, ['menu_access' => [$ppmp->id => 'inherit']])->assertSessionHas('success', fn ($m) => str_starts_with($m, 'Access updated'));
        $this->assertDatabaseCount('permission_user_overrides', 0);
        $this->assertTrue($this->staff->fresh()->canAccessPermission('manage ppmp'));
    }

    public function test_nobody_can_remove_their_own_user_access(): void
    {
        $access = $this->menu('User Access');

        $this->actingAs($this->admin)->put(route('core.access.update', $this->admin), ['menu_access' => [$access->id => 'deny']])
            ->assertSessionHasErrors('access');

        $this->assertDatabaseCount('menu_user_overrides', 0);
        $this->assertDatabaseCount('permission_user_overrides', 0);
        $this->get(route('core.access.index'))->assertOk();
    }

    public function test_a_super_admin_can_be_denied_preparing_the_app(): void
    {
        $other = User::factory()->create(['is_activated' => 1]);
        $other->assignRole('Super Admin');
        $appManage = Permission::where('name', 'menu.app-manage')->value('id');
        $apps = app(\App\Services\Procurement\AppService::class);

        $this->assertTrue($apps->canManage($other, \App\Enums\Region::Vis));
        $this->save($other, ['perm_access' => [$appManage => 'deny']]);
        $this->assertFalse($apps->canManage($other->fresh(), \App\Enums\Region::Vis));
    }

    public function test_bad_values_and_rows_not_on_the_screen_are_refused(): void
    {
        $ppmp = $this->menu('PPMP');
        $this->actingAs($this->admin)->put(route('core.access.update', $this->staff), ['menu_access' => [$ppmp->id => 'yes']])
            ->assertSessionHasErrors('menu_access.' . $ppmp->id);

        // A page's "manage X" permission follows the page; it is not set on its own
        $manage = Permission::where('name', 'manage ppmp')->value('id');
        $this->save($this->staff, ['perm_access' => [$manage => 'deny']]);
        $this->assertDatabaseCount('permission_user_overrides', 0);
        $this->assertTrue($this->staff->fresh()->canAccessPermission('manage ppmp'));

        // A key written with a leading zero is the same permission, not an error
        $clear = Permission::where('name', 'menu.activity-logs-clear')->value('id');
        $this->save($this->staff, ['perm_access' => ['0' . $clear => 'allow']]);
        $this->assertTrue($this->staff->fresh()->canAccessPermission('menu.activity-logs-clear'));
    }
}
