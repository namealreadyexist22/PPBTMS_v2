<?php

namespace App\Core\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Spatie\Permission\Models\Permission;

class Menu extends Model
{
    protected $fillable = [
        'parent_id', 'name', 'nav_name', 'icon', 'route', 'url',
        'permission_name', 'order', 'is_active', 'is_nav',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'is_nav' => 'boolean',
    ];

    protected static function booted(): void
    {
        static::creating(function (Menu $menu) {
            if (empty($menu->permission_name)) {
                $menu->permission_name = static::generateUniquePermissionName($menu->name);
            }
        });

        static::created(function (Menu $menu) {
            Permission::firstOrCreate(['name' => $menu->permission_name, 'guard_name' => 'web']);
        });

        static::deleting(function (Menu $menu) {
            Permission::where('name', $menu->permission_name)->delete();
        });
    }

    public static function generateUniquePermissionName(string $name): string
    {
        $base = 'menu.' . Str::slug($name, '-');
        $slug = $base;
        $i = 1;

        while (static::where('permission_name', $slug)->exists()) {
            $slug = $base . '-' . $i++;
        }

        return $slug;
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'parent_id');
    }

    /**
     * Structure is capped at root -> menu -> submenu (2 real levels;
     * a wrapping group like "Settings" doesn't count against this). A
     * true submenu is always a leaf — it never gets its own "Submenus"
     * button, so nesting can't go any deeper than that.
     */
    public function canHaveSubmenus(): bool
    {
        if (is_null($this->parent_id)) {
            return true;
        }

        return is_null($this->parent?->parent_id);
    }

    public function children(): HasMany
    {
        return $this->hasMany(Menu::class, 'parent_id')->orderBy('order');
    }

    /**
     * Every descendant's name, flattened recursively (children,
     * grandchildren, etc.) — used for the "Submenus" preview column so
     * the whole subtree shows at a glance, not just direct children.
     */
    public function allDescendantsFlat(): \Illuminate\Support\Collection
    {
        $names = collect();

        foreach ($this->children as $child) {
            $names->push($child->name);
            $names = $names->merge($child->allDescendantsFlat());
        }

        return $names;
    }

    public function overrides(): HasMany
    {
        return $this->hasMany(MenuUserOverride::class);
    }

    public function linkedPermissions(): HasMany
    {
        return $this->hasMany(Permission::class, 'menu_id');
    }

    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    public function scopeTopLevel($query)
    {
        return $query->whereNull('parent_id');
    }

    public function resolvedUrl(): string
    {
        if ($this->route && \Illuminate\Support\Facades\Route::has($this->route)) {
            return route($this->route);
        }

        return $this->url ?: '#';
    }

    /**
     * Functional pages (ones with an actual route), grouped by their
     * parent's name, each with its linked action permissions eager
     * loaded — the shared data shape behind the card-grid layout used
     * on both the Roles and User Access screens. Groups are sorted to
     * match each parent's actual position in the sidebar (its own
     * `order` value), not just whatever order the leaf items happen to
     * appear in.
     */
    public static function leafMenusGroupedForCards()
    {
        $leafMenus = static::with(['parent', 'linkedPermissions'])
            ->whereNotNull('route')
            ->orderBy('order')
            ->get();

        $grouped = $leafMenus->groupBy(fn ($menu) => $menu->parent?->name ?? 'Main Menu');

        $topLevelOrders = static::whereNull('parent_id')->pluck('order', 'name');

        return $grouped->sortBy(fn ($menusInGroup, $groupName) => $topLevelOrders[$groupName] ?? 0);
    }
}