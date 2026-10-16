<?php

namespace App\Core\Http\Controllers;

use App\Core\Models\Menu;
use App\Core\Models\MenuUserOverride;
use App\Core\Models\PermissionUserOverride;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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
        $defaultSources = collect();
        $actionPermissions = collect();
        $pageRows = collect();
        $overrideCount = 0;

        if ($request->filled('user')) {
            $selectedUser = User::findOrFail($request->user);

            $groupedMenus = Menu::leafMenusGroupedForCards();

            $defaultSources = $this->defaultSources($selectedUser);

            $unassignedGrouped = Permission::where('name', 'not like', 'menu.%')
                ->whereNull('menu_id')
                ->orderBy('group')->orderBy('name')
                ->get()
                ->groupBy(fn ($p) => $p->group ?: 'General');

            $actionPermissions = $this->actionPermissions($groupedMenus)->keyBy('name');

            $menuOverrides = $selectedUser->menuOverrides()->pluck('access', 'menu_id');
            $permissionOverrides = $selectedUser->permissionOverrides()->pluck('access', 'permission_id');

            // A page row: its pages are guarded by its "manage X" permission when it has one (else by
            // its own menu.* permission), its sidebar link by menu.*. A "manage X" override without a
            // page override (made before the two were linked) shows on the page row, so it can be seen
            // and cleared.
            $pageRows = $groupedMenus->flatten()->mapWithKeys(function (Menu $menu) use ($menuOverrides, $permissionOverrides, $defaultSources) {
                $manage = $this->managePermission($menu);

                return [$menu->id => [
                    // The "manage X" override is what guards the pages, so it is what the row shows
                    'value'      => ($manage ? $permissionOverrides->get($manage->id) : null) ?? $menuOverrides->get($menu->id),
                    'source'     => $defaultSources->get($manage?->name ?? $menu->permission_name),
                    'linkSource' => $defaultSources->get($menu->permission_name),
                ]];
            });

            $overrideCount = $pageRows->whereNotNull('value')->count()
                + $permissionOverrides->keys()->intersect($this->editablePermissionIds($groupedMenus->flatten()))->count();
        }

        return view('admin.users.access', compact(
            'users', 'selectedUser', 'groupedMenus', 'unassignedGrouped',
            'menuOverrides', 'permissionOverrides', 'defaultSources', 'actionPermissions', 'pageRows', 'overrideCount'
        ));
    }

    /** The page's linked "manage X" permission, which guards its routes (null when it has none). */
    protected function managePermission(Menu $menu)
    {
        return $menu->linkedPermissions->first(fn ($p) => str_starts_with($p->name, 'manage '));
    }

    /**
     * What the user has without any override, as permission name => where it
     * comes from: their roles, or permissions given to them directly. A Super
     * Admin has everything.
     */
    protected function defaultSources(User $user)
    {
        if ($user->hasRole('Super Admin')) {
            return Permission::pluck('name')->mapWithKeys(fn ($name) => [$name => 'Super Admin']);
        }

        return $user->getDirectPermissions()->pluck('name')->mapWithKeys(fn ($name) => [$name => 'given directly'])
            ->merge($user->getPermissionsViaRoles()->pluck('name')->mapWithKeys(fn ($name) => [$name => 'role']));
    }

    /** Permissions of the hidden action gates (e.g. menu.ppmp-view-all) under the given pages. */
    protected function actionPermissions($groupedMenus)
    {
        $names = $groupedMenus->flatten()->flatMap(fn (Menu $menu) => $menu->actionChildren)->pluck('permission_name');

        return Permission::whereIn('name', $names)->get();
    }

    /**
     * Each row posts Default (inherit), Allow or Deny: menu_access[menu id] for
     * a page, perm_access[permission id] for an action or other permission.
     * Default removes the override, so the user follows their roles again. A
     * page's "manage X" permission (which guards its routes) gets the same
     * override automatically, same merge as the Roles screen; any other linked
     * permission (e.g. a *.destroy) has a row of its own.
     */
    public function update(Request $request, User $user)
    {
        $data = $request->validate([
            'menu_access'   => ['array'],
            'menu_access.*' => ['in:inherit,allow,deny'],
            'perm_access'   => ['array'],
            'perm_access.*' => ['in:inherit,allow,deny'],
        ]);

        $menus = Menu::leafMenusGroupedForCards()->flatten()->keyBy('id');
        $editable = $this->editablePermissionIds($menus);
        $names = Permission::pluck('name', 'id');
        $changes = [];

        DB::transaction(function () use ($data, $user, $menus, $editable, $names, &$changes) {
            foreach ($data['menu_access'] ?? [] as $menuId => $access) {
                if (! $menu = $menus->get((int) $menuId)) {
                    continue;
                }

                $changed = $this->setOverride(new MenuUserOverride, ['user_id' => $user->id, 'menu_id' => $menu->id], $access);

                // The page's "manage X" permission guards its routes: it gets the same override
                if ($manage = $this->managePermission($menu)) {
                    $changed = $this->setOverride(new PermissionUserOverride, ['user_id' => $user->id, 'permission_id' => $manage->id], $access) || $changed;
                }

                if ($changed) {
                    $changes[] = "{$menu->name}: {$access}";
                }
            }

            foreach ($data['perm_access'] ?? [] as $permissionId => $access) {
                $permissionId = (int) $permissionId;

                if (! $editable->contains($permissionId)) {
                    continue;
                }

                if ($this->setOverride(new PermissionUserOverride, ['user_id' => $user->id, 'permission_id' => $permissionId], $access)) {
                    $changes[] = ($names[$permissionId] ?? "#{$permissionId}") . ": {$access}";
                }
            }

            // Nobody can lock themselves out of this screen (rolled back with the rest)
            if ($user->is(auth()->user()) && ! $user->canAccessPermission('manage access')) {
                throw ValidationException::withMessages(['access' => 'You cannot remove your own access to User Access.']);
            }
        });

        activity()->causedBy(auth()->user())->performedOn($user)
            ->withProperties(['changes' => $changes])
            ->log("updated access overrides for user \"{$user->fullname}\"");

        return back()->with('success', $changes
            ? "Access updated for {$user->fullname}."
            : "Nothing changed for {$user->fullname}.");
    }

    /** inherit removes the override; allow / deny saves it. True when something changed. */
    protected function setOverride(MenuUserOverride|PermissionUserOverride $model, array $keys, string $access): bool
    {
        $current = $model->newQuery()->where($keys)->first();

        if ($access === 'inherit') {
            return (bool) $current?->delete();
        }

        if ($current?->access === $access) {
            return false;
        }

        $model->newQuery()->updateOrCreate($keys, ['access' => $access]);

        return true;
    }

    /**
     * Permissions with a row of their own on the screen: the hidden action
     * gates, a page's other linked permissions, and permissions not linked to
     * any page. A page's "manage X" permission is not one: it follows the page.
     */
    protected function editablePermissionIds($menus)
    {
        $linked = $menus->flatMap(fn (Menu $menu) => $menu->linkedPermissions)
            ->reject(fn ($p) => str_starts_with($p->name, 'manage '))
            ->pluck('id');

        return Permission::where('name', 'not like', 'menu.%')->whereNull('menu_id')->pluck('id')
            ->merge($linked)
            ->merge($this->actionPermissions($menus)->pluck('id'))
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }
}
