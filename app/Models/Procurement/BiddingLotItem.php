<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** A PR / JR line in a lot of a bidding. */
class BiddingLotItem extends Model
{
    protected $fillable = ['bidding_lot_id', 'purchase_request_item_id', 'sort_order'];

    public function lot(): BelongsTo
    {
        return $this->belongsTo(BiddingLot::class, 'bidding_lot_id');
    }

    public function line(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequestItem::class, 'purchase_request_item_id');
    }
}
