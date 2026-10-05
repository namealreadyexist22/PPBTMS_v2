<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class FundSource extends Model
{
    protected $fillable = ['code', 'name', 'fund_group', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean', 'fund_group' => \App\Enums\FundGroup::class];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
