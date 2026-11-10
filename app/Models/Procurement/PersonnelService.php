<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Personnel Services (salaries) of a department for a fiscal year and fund. Printed on the COB
 * Breakdown per Office / Department; not part of the CO + MOOE budget the PPMPs are checked against.
 */
class PersonnelService extends Model
{
    protected $fillable = ['fiscal_year', 'department_id', 'fund_group', 'amount', 'created_by', 'updated_by'];

    protected function casts(): array
    {
        return ['fiscal_year' => 'integer', 'fund_group' => FundGroup::class, 'amount' => 'decimal:2'];
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'department_id');
    }

    public function history(): HasMany
    {
        return $this->hasMany(PersonnelServiceChange::class)->latest('id');
    }
}
