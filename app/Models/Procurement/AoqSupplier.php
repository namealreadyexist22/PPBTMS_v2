<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/** A supplier that submitted a quotation for the AOQ's RFQ, with its quotation / proposal PDF. */
class AoqSupplier extends Model
{
    protected $fillable = [
        'aoq_id', 'supplier_id', 'name', 'address', 'tin', 'contact', 'is_responsive', 'remarks',
        'attachment_name', 'attachment_path', 'attachment_size', 'sort_order',
    ];

    protected function casts(): array
    {
        return ['is_responsive' => 'boolean'];
    }

    public function aoq(): BelongsTo
    {
        return $this->belongsTo(Aoq::class);
    }

    /** Its entry in the supplier directory. */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    public function quotes(): HasMany
    {
        return $this->hasMany(AoqQuote::class);
    }
}
