<?php

namespace App\Models\Procurement;

use App\Enums\FundGroup;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemCategory extends Model
{
    protected $fillable = ['code', 'name', 'restricted_office_id', 'restricted_fund_group', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'restricted_fund_group' => FundGroup::class];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }

    /** The only unit that may procure this category's items (e.g. MIS for ICT Equipment). */
    public function restrictedOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'restricted_office_id');
    }

    /**
     * May this office procure this category's items with this fund? Yes when unrestricted, when the
     * rule is for another fund (e.g. COB only, and this is SIDA), or when the office is the designated
     * unit or one under it.
     */
    public function allows(Office $office, ?FundGroup $fund): bool
    {
        if (! $this->restricted_office_id) {
            return true;
        }

        if ($this->restricted_fund_group && $fund && $this->restricted_fund_group !== $fund) {
            return true;
        }

        return in_array($office->id, Office::withDescendantIds([$this->restricted_office_id])->all(), true);
    }

    /** e.g. "Procured by MIS only (COB)" */
    public function restrictionLabel(): ?string
    {
        if (! $this->restricted_office_id) {
            return null;
        }

        return 'Procured by ' . ($this->restrictedOffice?->shortName() ?? 'a designated unit') . ' only'
            . ($this->restricted_fund_group ? ' (' . $this->restricted_fund_group->label() . ')' : '');
    }
}
