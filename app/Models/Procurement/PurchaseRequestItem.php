<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One line of a PR / JR, charged to a PPMP project line (followed across amendments by line_uuid). */
class PurchaseRequestItem extends Model
{
    protected $fillable = [
        'purchase_request_id', 'ppmp_item_id', 'line_uuid', 'stock_no', 'unit', 'description', 'specifications',
        'quantity', 'unit_cost', 'total_cost', 'nature_of_work', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2', 'unit_cost' => 'decimal:2', 'total_cost' => 'decimal:2'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(PurchaseRequest::class, 'purchase_request_id');
    }

    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class);
    }
}
