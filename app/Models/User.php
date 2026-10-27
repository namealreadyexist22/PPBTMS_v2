<?php

namespace App\Models;


use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;

#[Fillable([
    'img_slug',
    'name_title',  // Atty., Engr., Dr., ... (part of fullname)
    'fname',
    'lname',
    'minitial',
    'name_suffix', // Jr., Sr., III, ... (part of fullname)
    'designation',
    'office_id',
    'region',      // virtual: stored in categories (see region())
    'username',
    'email',
    'email_verified_at',
    'password',
    'remember_token',
    'google_id',
    'categories',
    'roletype',
    'is_activated',
    'user_created',
    'user_updated',
    'last_login_ip'
])]
#[Hidden([
    'password',
    'remember_token',
    'google_id'
])]
class User extends Authenticatable
{

    use HasFactory, Notifiable, HasRoles;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_activated'      => 'boolean',
        ];
    }

    /**
     * Region from users.categories (1 = Luzon/Mindanao, 2 = Visayas).
     * Setting $user->region = 'vis' writes categories = 2.
     */
    protected function region(): \Illuminate\Database\Eloquent\Casts\Attribute
    {
        return \Illuminate\Database\Eloquent\Casts\Attribute::make(
            get: fn ($value, array $attributes) => \App\Enums\Region::fromCategory($attributes['categories'] ?? 1),
            set: fn ($value) => ['categories' => ($value instanceof \App\Enums\Region ? $value : \App\Enums\Region::from($value))->category()],
        );
    }

    /** Home office. */
    public function office()
    {
        return $this->belongsTo(\App\Models\Procurement\Office::class);
    }

    /**
     * Extra offices whose approved PPMPs this user may charge PRs to
     * (e.g. SIDA-SCP and SIDA-HRD). They can view those PPMPs but not edit them.
     */
    public function offices()
    {
        return $this->belongsToMany(\App\Models\Procurement\Office::class)->withTimestamps();
    }

    /** Offices this user may create PRs for: home office plus extra offices. */
    public function prOfficeIds(): array
    {
        return $this->offices()->pluck('offices.id')
            ->push($this->office_id)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    public function menuOverrides()
    {
        return $this->hasMany(\App\Core\Models\MenuUserOverride::class);
    }

    public function permissionOverrides()
    {
        return $this->hasMany(\App\Core\Models\PermissionUserOverride::class);
    }

    /**
     * Override-aware permission check: a per-user allow/deny beats the
     * role-based (or direct) permission. Use this instead of $user->can()
     * anywhere an individual exception (like Kevin losing user.destroy) matters.
     * A page's override (User Access) counts for its own permission, e.g.
     * menu.division-ppmp; its "manage X" permission gets the same override.
     */
    public function canAccessPermission(string $permissionName): bool
    {
        $access = $this->accessOverrides($permissionName)[$permissionName] ?? null;

        if ($access !== null) {
            return $access === 'allow';
        }

        return $this->can($permissionName);
    }

    /**
     * This user's overrides as permission name => 'allow'|'deny': permission
     * overrides, then page overrides under the page's permission (menu.ppmp).
     * A permission override wins over a page override of the same name.
     */
    public function accessOverrides(?string $only = null): array
    {
        $permissions = \App\Core\Models\PermissionUserOverride::query()
            ->join('permissions', 'permissions.id', '=', 'permission_user_overrides.permission_id')
            ->where('permission_user_overrides.user_id', $this->id)
            ->when($only !== null, fn ($q) => $q->where('permissions.name', $only))
            ->pluck('permission_user_overrides.access', 'permissions.name')->all();

        $pages = \App\Core\Models\MenuUserOverride::query()
            ->join('menus', 'menus.id', '=', 'menu_user_overrides.menu_id')
            ->where('menu_user_overrides.user_id', $this->id)
            ->when($only !== null, fn ($q) => $q->where('menus.permission_name', $only))
            ->pluck('menu_user_overrides.access', 'menus.permission_name')->all();

        return $permissions + $pages;
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_data) {
            return route('app.users.avatar', $this->id);
        }

        return route('app.users.avatar.default');
    }
}

