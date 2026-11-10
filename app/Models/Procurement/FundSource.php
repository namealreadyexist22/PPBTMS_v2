<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FundSource extends Model
{
    protected $fillable = ['code', 'name', 'fund_group', 'restricted_office_id', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fund_group' => \App\Enums\FundGroup::class];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The only office (with the units under it) that may charge this fund, e.g. BRDE for Corporate(BRDE) */
    public function restrictedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'restricted_office_id');
    }

    /** May this office charge projects to this fund? */
    public function allows(Office $office): bool
    {
        return ! $this->restricted_office_id
            || in_array($office->id, Office::withDescendantIds([$this->restricted_office_id])->all(), true);
    }
}
