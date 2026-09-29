<?php

namespace App\Core\Http\Controllers;

use App\Core\DataTables\MenusDataTable;
use App\Core\Models\Menu;
use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;

class MenuController extends Controller
{
    public function index(MenusDataTable $dataTable)
    {
        return $dataTable->render('admin.menus.index');
    }

    public function entry(Request $request)
    {
        $menu = $request->filled('id') ? Menu::findOrFail($request->id) : new Menu();

        if (! $menu->exists && $request->filled('parent_id')) {
            $menu->parent_id = $request->parent_id;
        }

        $parents = Menu::whereNull('parent_id')
            ->when($menu->exists, fn ($q) => $q->where('id', '!=', $menu->id))
            ->orderBy('order')
            ->get();

        $basePermissionName = $menu->exists
            ? $menu->linkedPermissions->first(fn ($p) => str_starts_with($p->name, 'manage '))?->name
            : null;

        return view('admin.menus._form', compact('menu', 'parents', 'basePermissionName'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $menu = Menu::create($data);

        activity()->causedBy(auth()->user())->performedOn($menu)->log("created menu \"{$menu->name}\"");

        $this->syncBasePermission($request, $menu);
        $this->createQuickActions($request, $menu);

        return response()->json(['status' => 'success', 'message' => 'Menu item created.']);
    }

    public function update(Request $request, Menu $menu)
    {
        $data = $this->validated($request, $menu->id);
        $menu->update($data);

        activity()->causedBy(auth()->user())->performedOn($menu)->log("updated menu \"{$menu->name}\"");

        $this->syncBasePermission($request, $menu);
        $this->createQuickActions($request, $menu);

        return response()->json(['status' => 'success', 'message' => 'Menu item updated.']);
    }

    public function destroy(Menu $menu)
    {
        $name = $menu->name;

        activity()->causedBy(auth()->user())->performedOn($menu)->log("deleted menu \"{$name}\"");

        $menu->delete(); // children cascade-delete along with it

        return response()->json(['status' => 'success', 'message' => 'Menu item deleted.']);
    }

    public function submenus(Menu $menu, MenusDataTable $dataTable)
    {
        return $dataTable->forParent($menu->id)->render('admin.menus.submenus', compact('menu'));
    }

    /**
     * Links (or creates) this menu's base functional gate by exact name
     * — never renames an existing permission, since your route
     * middleware hardcodes these strings. Changing the field just moves
     * which permission is currently linked to this menu.
     */
    protected function syncBasePermission(Request $request, Menu $menu): void
    {
        $newName = trim((string) $request->input('base_permission_name', ''));

        Permission::where('menu_id', $menu->id)
            ->where('name', 'like', 'manage %')
            ->where('name', '!=', $newName)
            ->update(['menu_id' => null]);

        if ($newName === '') {
            return;
        }

        Permission::updateOrCreate(
            ['name' => $newName],
            ['menu_id' => $menu->id, 'group' => $menu->name, 'guard_name' => 'web']
        );
    }

    /**
     * Every checked "quick action" becomes a child Menu — it always gets
     * its own auto-generated permission just like any menu does; is_nav
     * just decides whether it also shows up as its own sidebar link.
     */
    protected function createQuickActions(Request $request, Menu $menu): void
    {
        $includedActions = collect($request->input('quick_actions', []));
        $navActions = collect($request->input('quick_actions_nav', []));

        if ($includedActions->isEmpty()) {
            return;
        }

        $friendlyNavNames = [
            'Create'  => 'Add',
            'Store'   => 'Save',
            'Edit'    => 'Edit',
            'Update'  => 'Update',
            'Show'    => 'View',
            'Destroy' => 'Delete',
        ];

        $order = ($menu->children()->max('order') ?? 0) + 1;
        $createdCount = 0;

        foreach ($includedActions as $action) {
            Menu::create([
                'parent_id' => $menu->id,
                'name' => "{$menu->name} {$action}",
                'nav_name' => $friendlyNavNames[$action] ?? $action,
                'is_nav' => $navActions->contains($action),
                'order' => $order++,
                'is_active' => true,
            ]);

            $createdCount++;
        }

        if ($createdCount > 0) {
            activity()->causedBy(auth()->user())->performedOn($menu)
                ->log("auto-created {$createdCount} submenu(s) for menu \"{$menu->name}\"");
        }
    }

    protected function validated(Request $request, ?int $ignoreId = null): array
    {
        $data = $request->validate([
            'name'      => ['required', 'string', 'max:255'],
            'nav_name'  => ['nullable', 'string', 'max:255'],
            'parent_id' => [
                'nullable', 'exists:menus,id',
                $ignoreId ? \Illuminate\Validation\Rule::notIn([$ignoreId]) : 'nullable',
            ],
            'icon'      => ['nullable', 'string', 'max:100'],
            'route'     => ['nullable', 'string', 'max:255'],
            'url'       => ['nullable', 'string', 'max:255'],
            'order'     => ['nullable', 'integer', 'min:0'],
        ]);

        $data['is_active'] = $request->boolean('is_active');
        $data['is_nav'] = $request->boolean('is_nav');

        return $data;
    }
}