<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A document each bidder's bid is checked for (e.g. PhilGEPS certificate, bid security), kept in Procurement Lookups. */
class BidDocument extends Model
{
    protected $fillable = ['code', 'name', 'is_active'];

    protected function casts(): array
    {
        return ['is_active' => 'boolean'];
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}
