<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A PR / JR line on an RFQ; description, quantity and ABC are the line's. */
class RfqItem extends Model
{
    protected $fillable = ['rfq_id', 'purchase_request_item_id', 'sort_order'];

    public function rfq(): BelongsTo
    {
        return $this->belongsTo(Rfq::class);
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }
}
