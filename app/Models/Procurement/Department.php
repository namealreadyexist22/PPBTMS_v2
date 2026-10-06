<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A department as the Budget office groups offices (e.g. PPSPD: offices 05000 to 05020).
 * It receives the budget allocation, in one fund (COB, or SIDA for the SIDA departments),
 * and every office in it shares that budget.
 */
class Department extends Model
{
    protected $fillable = ['code', 'name', 'fund_group', 'is_active'];

    protected function casts(): array
    {
        return ['fund_group' => FundGroup::class, 'is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** e.g. "PPSPD — PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT" */
    public function label(): string
    {
        return $this->code === $this->name ? $this->code : "{$this->code} — {$this->name}";
    }

    public function offices(): HasMany
    {
        return $this->hasMany(Office::class);
    }
}
