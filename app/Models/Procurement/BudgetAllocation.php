<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Approved budget of an office for a fiscal year and fund (COB / SIDA), CO and MOOE together.
 * It caps the PPMPs of the office and everything under it.
 */
class BudgetAllocation extends Model
{
    protected $fillable = ['fiscal_year', 'office_id', 'fund_group', 'amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return [
            'fiscal_year' => 'integer',
            'fund_group'  => FundGroup::class,
            'amount'      => 'decimal:2',
        ];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function history(): HasMany
    {
        return $this->hasMany(BudgetAllocationChange::class)->latest('id');
    }
}
