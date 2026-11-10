<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * MOOE object of expenditure, a column of the COB's Details of MOOE (Form B):
 * Traveling, Training, Office Supplies, Semi-Expendable, Representation, Other MOOE, ...
 */
class ExpenseClass extends Model
{
    /** The class semi-expendable projects fall under when none is picked */
    public const SEMI_EXPENDABLE = 'SEMI';

    protected $fillable = ['code', 'name', 'sort_order', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'sort_order' => 'integer'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }
}
