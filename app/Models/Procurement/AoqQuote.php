<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A supplier's offer for one RFQ item; no unit price = no quote. */
class AoqQuote extends Model
{
    protected $fillable = ['aoq_supplier_id', 'rfq_item_id', 'unit_price', 'brand', 'is_compliant', 'is_awarded'];

    protected function casts(): array
    {
        return ['unit_price' => 'decimal:2', 'is_compliant' => 'boolean', 'is_awarded' => 'boolean'];
    }

    public function supplier(): BelongsTo
    {
        return $this->belongsTo(AoqSupplier::class, 'aoq_supplier_id');
    }

    public function rfqItem(): BelongsTo
    {
        return $this->belongsTo(RfqItem::class);
    }
}
