<?php

namespace App\Core\Http\Controllers;

use App\Core\DataTables\RolesDataTable;
use App\Core\Models\Menu;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

class RoleController extends Controller
{
    public function index(RolesDataTable $dataTable)
    {
        return $dataTable->render('admin.roles.index');
    }

    public function entry()
    {
        return view('admin.roles._form');
    }

    public function store(Request $request)
    {
        $request->validate(['name' => ['required', 'string', 'max:255', 'unique:roles,name']]);
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        activity()->causedBy(auth()->user())->performedOn($role)->log("created role \"{$role->name}\"");

        return response()->json(['status' => 'success', 'message' => 'Role created.']);
    }

    public function destroy(Role $role)
    {
        if ($role->name === 'Super Admin') {
            return response()->json(['status' => 'error', 'message' => 'The Super Admin role can\'t be deleted.'], 422);
        }

        $name = $role->name;
        activity()->causedBy(auth()->user())->performedOn($role)->log("deleted role \"{$name}\"");
        $role->delete();

        return response()->json(['status' => 'success', 'message' => 'Role deleted.']);
    }

    /**
     * Card-grid view: functional menus grouped by parent, each with its
     * linked action permissions, plus a fallback section for anything
     * with no menu link.
     */
    public function permissions(Role $role)
    {
        $groupedMenus = Menu::leafMenusGroupedForCards();

        $unassignedGrouped = Permission::where('name', 'not like', 'menu.%')
            ->whereNull('menu_id')
            ->orderBy('group')->orderBy('name')
            ->get()
            ->groupBy(fn ($p) => $p->group ?: 'General');

        $rolePermissionNames = $role->permissions->pluck('name');

        return view('admin.roles.permissions', compact(
            'role', 'groupedMenus', 'unassignedGrouped', 'rolePermissionNames'
        ));
    }

    public function updatePermissions(Request $request, Role $role)
    {
        $selectedMenuIds = collect($request->input('menu_ids', []))->map(fn ($id) => (int) $id);
        $selectedMenus = Menu::with('linkedPermissions')->whereIn('id', $selectedMenuIds)->get();

        $selectedMenuPermissionNames = $selectedMenus->pluck('permission_name');

        // Folded into the View/Access toggle in the UI — restore them here
        // so checking a menu still grants its "manage X" permission too.
        $impliedManagePermissionNames = $selectedMenus
            ->flatMap(fn ($menu) => $menu->linkedPermissions)
            ->filter(fn ($p) => str_starts_with($p->name, 'manage '))
            ->pluck('name');

        $selectedActionPermissionNames = collect($request->input('permission_names', []));

        $role->syncPermissions(
            $selectedMenuPermissionNames
                ->merge($impliedManagePermissionNames)
                ->merge($selectedActionPermissionNames)
                ->unique()
        );

        activity()->causedBy(auth()->user())->performedOn($role)->log("updated permissions for role \"{$role->name}\"");

        return back()->with('success', "Permissions updated for role \"{$role->name}\".");
    }
}