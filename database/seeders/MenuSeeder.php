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

        // Procurement pages, found by name so an existing menu keeps its id, permission and user access when it moves
        $page = fn (string $name, ?Menu $parent, array $attributes) => Menu::updateOrCreate(
            ['name' => $name],
            ['parent_id' => $parent?->id] + $attributes
        );

        // PR / JR: a link of its own, right under the Dashboard
        $page('Purchase Requests', null, ['nav_name' => 'PR / JR', 'icon' => 'fas fa-file-invoice', 'route' => 'procurement.requests.index', 'order' => 2]);

        // Planning: budget, PPMPs and the APP
        $planning = Menu::updateOrCreate(['name' => 'Planning', 'parent_id' => null], ['icon' => 'fas fa-clipboard-list', 'order' => 10]);
        $page('Budget Allocation', $planning, ['icon' => 'fas fa-coins', 'route' => 'procurement.budget.index', 'order' => 1]);
        $ppmp = $page('PPMP', $planning, ['icon' => 'fas fa-clipboard-list', 'route' => 'procurement.ppmp.index', 'order' => 2]);
        // Offices ask the procuring office (e.g. MIS for ICT) for items; it approves into its PPMP distribution
        $page('Item Requests', $planning, ['icon' => 'fas fa-hand-holding', 'route' => 'procurement.item-requests.index', 'order' => 3]);
        $page('Division PPMP', $planning, ['icon' => 'fas fa-layer-group', 'route' => 'procurement.division-ppmp.index', 'order' => 4]);
        $app = $page('APP', $planning, ['nav_name' => 'APP', 'icon' => 'fas fa-calendar-check', 'route' => 'procurement.app.index', 'order' => 5]);

        // Procurement: quotations, their evaluation and who quotes
        $procurement = Menu::updateOrCreate(['name' => 'Procurement', 'parent_id' => null], ['icon' => 'fas fa-shopping-cart', 'order' => 11]);
        $page('Requests for Quotation', $procurement, ['nav_name' => 'RFQ', 'icon' => 'fas fa-file-signature', 'route' => 'procurement.rfqs.index', 'order' => 1]);
        $page('Abstract of Quotations', $procurement, ['nav_name' => 'AOQ', 'icon' => 'fas fa-balance-scale', 'route' => 'procurement.aoqs.index', 'order' => 2]);
        $page('TWG Evaluation', $procurement, ['icon' => 'fas fa-clipboard-check', 'route' => 'procurement.twg.index', 'order' => 3]);
        $page('Suppliers', $procurement, ['icon' => 'fas fa-store', 'route' => 'procurement.suppliers.index', 'order' => 4]);
        $page('Standard Items', $procurement, ['icon' => 'fas fa-tags', 'route' => 'procurement.items.index', 'order' => 5]);

        // Procurement Setup: signatories and the lookup lists
        $setup = Menu::updateOrCreate(['name' => 'Procurement Setup', 'parent_id' => null], ['icon' => 'fas fa-tools', 'order' => 12]);
        $page('Signatories & BAC', $setup, ['icon' => 'fas fa-user-tie', 'route' => 'procurement.officials.index', 'order' => 1]);
        $page('Procurement Lookups', $setup, ['nav_name' => 'Procurement Lookups', 'icon' => 'fas fa-list-alt', 'route' => 'core.lookups.index', 'order' => 2]);

        // Groups no longer used (Canvassing was the first name of Procurement), once their pages have moved
        Menu::where('name', 'Canvassing')->whereNull('parent_id')->whereNull('route')->doesntHave('children')->get()->each->delete();

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