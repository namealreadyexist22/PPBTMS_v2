<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Menu;
use App\Core\Models\MenuUserOverride;
use App\Core\Models\PermissionUserOverride;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class UserAccessController extends Controller
{
    public function index(Request $request)
    {
        $users = User::query()
            ->with('roles')
            ->when($request->q, fn ($q) => $q->where('fname', 'like', "%{$request->q}%")
                ->orWhere('lname', 'like', "%{$request->q}%")
                ->orWhere('email', 'like', "%{$request->q}%"))
            ->orderBy('fname')
            ->paginate(15)
            ->withQueryString();

        $selectedUser = null;
        $groupedMenus = collect();
        $unassignedGrouped = collect();
        $menuOverrides = collect();
        $permissionOverrides = collect();
        $rolePermissionNames = collect();

        if ($request->filled('user')) {
            $selectedUser = User::findOrFail($request->user);

            $groupedMenus = Menu::leafMenusGroupedForCards();

            $rolePermissionNames = $selectedUser->getPermissionsViaRoles()->pluck('name');

            $unassignedGrouped = Permission::where('name', 'not like', 'menu.%')
                ->whereNull('menu_id')
                ->orderBy('group')->orderBy('name')
                ->get()
                ->groupBy(fn ($p) => $p->group ?: 'General');

            $menuOverrides = $selectedUser->menuOverrides()->pluck('access', 'menu_id');
            $permissionOverrides = $selectedUser->permissionOverrides()->pluck('access', 'permission_id');
        }

        return view('admin.users.access', compact(
            'users', 'selectedUser', 'groupedMenus', 'unassignedGrouped',
            'menuOverrides', 'permissionOverrides', 'rolePermissionNames'
        ));
    }

    /**
     * Per menu: the "override" toggle decides whether an override row
     * exists at all; the "state" toggle (only meaningful when override is
     * on) decides allow vs deny. A menu's linked "manage X" permission is
     * mirrored to match automatically, same merge as the Roles screen —
     * it never gets its own override row in the UI. Any other linked
     * permission (e.g. a *.destroy) stays fully independent.
     */
    public function update(Request $request, User $user)
    {
        $groupedMenus = Menu::leafMenusGroupedForCards();
        $allMenus = $groupedMenus->flatten();

        foreach ($allMenus as $menu) {
            $overrideOn = $request->boolean("menu_override.{$menu->id}");
            $managePermission = $menu->linkedPermissions->first(fn ($p) => str_starts_with($p->name, 'manage '));

            if (! $overrideOn) {
                MenuUserOverride::where('user_id', $user->id)->where('menu_id', $menu->id)->delete();

                if ($managePermission) {
                    PermissionUserOverride::where('user_id', $user->id)->where('permission_id', $managePermission->id)->delete();
                }

                continue;
            }

            $state = $request->boolean("menu_state.{$menu->id}");

            MenuUserOverride::updateOrCreate(
                ['user_id' => $user->id, 'menu_id' => $menu->id],
                ['access' => $state ? 'allow' : 'deny']
            );

            if ($managePermission) {
                PermissionUserOverride::updateOrCreate(
                    ['user_id' => $user->id, 'permission_id' => $managePermission->id],
                    ['access' => $state ? 'allow' : 'deny']
                );
            }
        }

        // Any other linked permission (e.g. *.destroy) plus unassigned ones
        // stay independently toggleable — same pattern as before, just now
        // excluding the "manage X" ones already handled above.
        $otherPermissionIds = Permission::where('name', 'not like', 'menu.%')
            ->where('name', 'not like', 'manage %')
            ->pluck('id');

        foreach ($otherPermissionIds as $permissionId) {
            $overrideOn = $request->boolean("perm_override.$permissionId");

            if (! $overrideOn) {
                PermissionUserOverride::where('user_id', $user->id)->where('permission_id', $permissionId)->delete();
                continue;
            }

            $state = $request->boolean("perm_state.$permissionId");
            PermissionUserOverride::updateOrCreate(
                ['user_id' => $user->id, 'permission_id' => $permissionId],
                ['access' => $state ? 'allow' : 'deny']
            );
        }

        activity()->causedBy(auth()->user())->performedOn($user)->log("updated access overrides for user \"{$user->fullname}\"");

        return back()->with('success', "Access updated for {$user->fullname}.");
    }
}