<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier that submitted a quotation for the AOQ's RFQ. */
class AoqSupplier extends Model
{
    protected $fillable = ['aoq_id', 'name', 'address', 'tin', 'contact', 'is_responsive', 'remarks', 'sort_order'];

    protected function casts(): array
    {
        return ['is_responsive' => 'boolean'];
    }

    public function aoq(): BelongsTo
    {
        return $this->belongsTo(Aoq::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(AoqQuote::class);
    }
}
