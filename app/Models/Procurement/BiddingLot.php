<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A lot of a bidding, awarded on its own. Its ABC is the total of its lines. */
class BiddingLot extends Model
{
    protected $fillable = ['bidding_id', 'lot_no', 'title', 'abc'];

    protected function casts(): array
    {
        return ['abc' => 'decimal:2'];
    }

    /** e.g. "Lot 2 — Laptops", or "Lot 2". */
    public function name(): string
    {
        return "Lot {$this->lot_no}" . (filled($this->title) ? " — {$this->title}" : '');
    }

    public function bidding(): BelongsTo
    {
        return $this->belongsTo(Bidding::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(BiddingLotItem::class)->orderBy('sort_order')->orderBy('id');
    }
}
