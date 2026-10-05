<?php

namespace Database\Seeders;

use App\Core\Models\Menu;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class MenuSeeder extends Seeder
{
    public function run(): void
    {
        Menu::updateOrCreate(
            ['name' => 'Dashboard', 'parent_id' => null],
            ['icon' => 'fas fa-tachometer-alt', 'route' => 'app.main.home', 'order' => 1]
        );

        $settings = Menu::updateOrCreate(
            ['name' => 'Settings', 'parent_id' => null],
            ['icon' => 'fas fa-cog', 'order' => 90]
        );

        Menu::updateOrCreate(
            ['name' => 'Manage Users', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-user', 'route' => 'core.users.index', 'order' => 1]
        );

        Menu::updateOrCreate(
            ['name' => 'User Access', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-user-lock', 'route' => 'core.access.index', 'order' => 2]
        );

        Menu::updateOrCreate(
            ['name' => 'Menu Management', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-list', 'route' => 'core.menus.index', 'order' => 3]
        );

        Menu::updateOrCreate(
            ['name' => 'Roles & Permissions', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-user-shield', 'route' => 'core.roles.index', 'order' => 5]
        );

        Menu::updateOrCreate(
            ['name' => 'Activity Logs', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-clipboard-list', 'route' => 'core.logs.index', 'order' => 6]
        );

        Menu::updateOrCreate(
            ['name' => 'App Settings', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-sliders-h', 'route' => 'core.settings.index', 'order' => 7]
        );

        Menu::updateOrCreate(
            ['name' => 'Offices', 'parent_id' => $settings->id],
            ['icon' => 'fas fa-building', 'route' => 'core.offices.index', 'order' => 8]
        );

        Menu::updateOrCreate(
            ['name' => 'Procurement Lookups', 'parent_id' => $settings->id],
            ['nav_name' => 'Procurement Lookups', 'icon' => 'fas fa-list-alt', 'route' => 'core.lookups.index', 'order' => 9]
        );

        $procurement = Menu::updateOrCreate(
            ['name' => 'Procurement', 'parent_id' => null],
            ['icon' => 'fas fa-shopping-cart', 'order' => 10]
        );

        Menu::updateOrCreate(
            ['name' => 'Budget Allocation', 'parent_id' => $procurement->id],
            ['icon' => 'fas fa-coins', 'route' => 'procurement.budget.index', 'order' => 0]
        );

        $ppmp = Menu::updateOrCreate(
            ['name' => 'PPMP', 'parent_id' => $procurement->id],
            ['icon' => 'fas fa-clipboard-list', 'route' => 'procurement.ppmp.index', 'order' => 1]
        );

        Menu::updateOrCreate(
            ['name' => 'Division PPMP', 'parent_id' => $procurement->id],
            ['icon' => 'fas fa-layer-group', 'route' => 'procurement.division-ppmp.index', 'order' => 2]
        );

        $app = Menu::updateOrCreate(
            ['name' => 'APP', 'parent_id' => $procurement->id],
            ['nav_name' => 'APP', 'icon' => 'fas fa-calendar-check', 'route' => 'procurement.app.index', 'order' => 3]
        );

        // Hidden gate: the BAC Secretariat prepares the APP of its own region (menu.app-manage)
        Menu::firstOrCreate(
            ['name' => 'APP Manage', 'parent_id' => $app->id],
            ['nav_name' => 'Prepare APP (BAC Secretariat)', 'is_nav' => false, 'order' => 90, 'is_active' => true]
        );

        // Hidden gate: BAC / consolidators see every office's PPMP (menu.ppmp-view-all)
        Menu::firstOrCreate(
            ['name' => 'PPMP View All', 'parent_id' => $ppmp->id],
            ['nav_name' => 'View All', 'is_nav' => false, 'order' => 90, 'is_active' => true]
        );

        // Give Super Admin every menu permission that exists so far.
        $superAdmin = Role::where('name', 'Super Admin')->first();
        if ($superAdmin) {
            $superAdmin->givePermissionTo(Menu::pluck('permission_name'));
        }
    }
}