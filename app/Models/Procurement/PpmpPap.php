<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A PAP (Program/Activity/Project) group in a section PPMP,
 * e.g. "26-05012-01 ICT Infrastructure Management" with its projects under it.
 */
class PpmpPap extends Model
{
    protected $fillable = ['ppmp_id', 'code', 'title', 'sort_order'];

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PpmpItem::class)->orderBy('sort_order');
    }

    public function label(): string
    {
        return "{$this->code} - {$this->title}";
    }
}
