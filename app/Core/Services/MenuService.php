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
        $overrides = $user->menuOverrides()
            ->get(['menu_id', 'access'])
            ->keyBy('menu_id');

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

    protected function filterTree(Collection $menus, User $user, Collection $overrides): Collection
    {
        return $menus
            ->map(function (Menu $menu) use ($user, $overrides) {
                $menu->setRelation('children', $this->filterTree($menu->children, $user, $overrides));
                return $menu;
            })
            ->filter(function (Menu $menu) use ($user, $overrides) {
                $accessible = $this->canAccess($menu, $user, $overrides);
                $hasVisibleChildren = $menu->children->isNotEmpty();

                return $accessible || $hasVisibleChildren;
            })
            ->values();
    }

    public function canAccess(Menu $menu, User $user, ?Collection $overrides = null): bool
    {
        $overrides ??= $user->menuOverrides()->get(['menu_id', 'access'])->keyBy('menu_id');

        if ($override = $overrides->get($menu->id)) {
            return $override->access === 'allow';
        }

        return $user->can($menu->permission_name);
    }
}