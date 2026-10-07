<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One office's share of a procurement project's items, by the procuring unit's assessment
 * (e.g. MIS: 3 of the 12 laptops go to PPSPD). Keyed by the project line, so it stays with the
 * project across amendments; later the basis for issuance (ICS / PAR).
 */
class PpmpItemDistribution extends Model
{
    protected $fillable = ['line_uuid', 'office_id', 'quantity', 'recipient', 'remarks', 'created_by'];

    protected function casts(): array
    {
        return ['quantity' => 'decimal:2'];
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }
}
