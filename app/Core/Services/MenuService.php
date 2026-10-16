<?php

namespace App\Core\Services;

use App\Core\Models\Menu;
use App\Models\User;
use Illuminate\Support\Collection;

class MenuService
{
    /**
     * Build the full menu tree, filtered to only what this user may see.
     * is_nav = false items never enter the tree at all — they exist as
     * permission gates only (e.g. Destroy), not as sidebar links.
     */
    public function getMenuTreeForUser(User $user): Collection
    {
        $overrides = $user->accessOverrides();

        $all = Menu::query()->active()->where('is_nav', true)->orderBy('order')->get();

        $tree = $this->buildTree($all, null);

        return $this->filterTree($tree, $user, $overrides);
    }

    protected function buildTree(Collection $menus, ?int $parentId): Collection
    {
        return $menus
            ->where('parent_id', $parentId)
            ->values()
            ->map(function (Menu $menu) use ($menus) {
                $menu->setRelation('children', $this->buildTree($menus, $menu->id));
                return $menu;
            });
    }

    /**
     * A plain group (submenus but no page of its own, like Settings) shows only
     * while one of its submenus does: when every page under it is denied, it goes too.
     */
    protected function filterTree(Collection $menus, User $user, array $overrides): Collection
    {
        return $menus
            ->filter(function (Menu $menu) use ($user, $overrides) {
                $isGroup = $menu->children->isNotEmpty() && ! $menu->route && ! $menu->url;
                $menu->setRelation('children', $this->filterTree($menu->children, $user, $overrides));

                if ($menu->children->isNotEmpty()) {
                    return true;
                }

                return ! $isGroup && $this->canAccess($menu, $user, $overrides);
            })
            ->values();
    }

    /** Same rule as the routes (User::canAccessPermission): the user's override first, then roles. */
    public function canAccess(Menu $menu, User $user, ?array $overrides = null): bool
    {
        $overrides ??= $user->accessOverrides($menu->permission_name);

        if ($access = $overrides[$menu->permission_name] ?? null) {
            return $access === 'allow';
        }

        return $user->can($menu->permission_name);
    }
}
