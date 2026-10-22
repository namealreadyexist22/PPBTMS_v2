<?php

namespace Database\Seeders;

use App\Core\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class ActionPermissionSeeder extends Seeder
{
    public function run(): void
    {
        $this->linkBasePermissions();
        $this->createDestroySubmenus();
        $this->grantAdminPageLinks();
    }

    /**
     * Base gates (manage menus, manage roles, etc.) are seeded by
     * RolePermissionSeeder as plain standalone permissions with no
     * menu_id. Since menus don't exist yet at that point, the link has
     * to happen here instead, once MenuSeeder has already run.
     */
    protected function linkBasePermissions(): void
    {
        $links = [
            'manage menus'    => 'Menu Management',
            'manage roles'    => 'Roles & Permissions',
            'manage users'    => 'Manage Users',
            'manage access'   => 'User Access',
            'manage settings' => 'App Settings',
            'manage offices'  => 'Offices',
            'manage ppmp'     => 'PPMP',
            'manage app'      => 'APP',
            'manage lookups'  => 'Procurement Lookups',
            'manage budget'   => 'Budget Allocation',
            'manage items'    => 'Standard Items',
            'manage requests' => 'Purchase Requests',
            'manage rfq'      => 'Requests for Quotation',
            'manage aoq'      => 'Abstract of Quotations',
            'evaluate aoq'    => 'TWG Evaluation',
            'manage suppliers' => 'Suppliers',
            'manage officials' => 'Signatories & BAC',
        ];

        foreach ($links as $permissionName => $menuName) {
            $menu = Menu::where('name', $menuName)->first();

            if (! $menu) {
                continue;
            }

            Permission::where('name', $permissionName)->update([
                'menu_id' => $menu->id,
                'group' => $menu->name,
            ]);
        }
    }

    /**
     * Admin is seeded with "manage X" permissions only, so its pages opened but
     * had no sidebar links (and User Access showed them as "no access"). Give it
     * the menu permission of each page it manages, as the Roles screen would.
     */
    protected function grantAdminPageLinks(): void
    {
        $admin = Role::where('name', 'Admin')->first();

        if (! $admin) {
            return;
        }

        $menuIds = $admin->permissions()->where('name', 'like', 'manage %')->whereNotNull('menu_id')->pluck('menu_id');

        $admin->givePermissionTo(Menu::whereIn('id', $menuIds)->pluck('permission_name')->all());
    }

    protected function createDestroySubmenus(): void
    {
        // top-level menu name => [submenu name, friendly nav_name]
        $submenus = [
            'Menu Management'     => ['Menu Management Destroy', 'Delete'],
            'Roles & Permissions' => ['Roles & Permissions Destroy', 'Delete'],
            'Manage Users'        => ['Manage Users Destroy', 'Delete'],
            'Activity Logs'       => ['Activity Logs Clear', 'Clear'],
            'Offices'             => ['Offices Destroy', 'Delete'],
            'PPMP'                => ['PPMP Destroy', 'Delete'],
        ];

        $createdPermissionNames = [];

        foreach ($submenus as $parentName => [$submenuName, $navName]) {
            $parent = Menu::where('name', $parentName)->first();

            if (! $parent) {
                continue;
            }

            $order = ($parent->children()->max('order') ?? 0) + 1;

            $submenu = Menu::firstOrCreate(
                ['name' => $submenuName, 'parent_id' => $parent->id],
                ['nav_name' => $navName, 'is_nav' => false, 'order' => $order, 'is_active' => true]
            );

            $createdPermissionNames[] = $submenu->permission_name;
        }

        $admin = Role::where('name', 'Admin')->first();
        if ($admin) {
            $admin->givePermissionTo($createdPermissionNames);
        }
    }
}