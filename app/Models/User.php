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
    'fname',
    'lname',
    'minitial',
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
     * role-based permission. Use this instead of $user->can() anywhere
     * an individual exception (like Kevin losing user.destroy) matters.
     */
    public function canAccessPermission(string $permissionName): bool
    {
        $override = $this->permissionOverrides()
            ->whereHas('permission', fn ($q) => $q->where('name', $permissionName))
            ->first();

        if ($override) {
            return $override->access === 'allow';
        }

        return $this->can($permissionName);
    }

    public function avatarUrl(): string
    {
        if ($this->avatar_data) {
            return route('app.users.avatar', $this->id);
        }

        return route('app.users.avatar.default');
    }
}

