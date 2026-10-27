<?php

namespace App\Models\Procurement;

use App\Enums\ItemRequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * An office's request for a standard item to the office that procures it (the item category's
 * designated unit, e.g. MIS for ICT Equipment), for a fiscal year. The procuring office approves
 * all, part or none of the quantity; the approved quantity goes to the distribution of the PPMP
 * project it picks (see ItemRequestService).
 */
class ItemRequest extends Model
{
    protected $fillable = [
        'uuid', 'request_no', 'fiscal_year', 'series', 'requesting_office_id', 'procuring_office_id', 'item_id',
        'item_code', 'item_name', 'unit_name', 'unit_cost', 'specifications', 'quantity', 'purpose', 'status', 'approved_quantity', 'decision_remarks', 'ppmp_item_id', 'line_uuid',
        'requested_by', 'decided_by', 'decided_at', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status'            => ItemRequestStatus::class,
            'quantity'          => 'decimal:2',
            'approved_quantity' => 'decimal:2',
            'unit_cost'         => 'decimal:2',
            'decided_at'        => 'datetime',
            'cancelled_at'      => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (ItemRequest $request) => $request->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** "ICT-0001 — Desktop Computer", as when requested. */
    public function itemLabel(): string
    {
        return ($this->item_code ?? $this->item?->code) . ' — ' . ($this->item_name ?? $this->item?->name);
    }

    public function unitName(): ?string
    {
        return $this->unit_name ?? $this->item?->unit?->name;
    }

    /** The TWG has changed the item's specifications since it was requested. */
    public function specsChanged(): bool
    {
        return $this->item && trim((string) $this->item->specifications) !== trim((string) $this->specifications);
    }

    public function isPending(): bool
    {
        return $this->status === ItemRequestStatus::Pending;
    }

    public function requestingOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'requesting_office_id');
    }

    public function procuringOffice(): BelongsTo
    {
        return $this->belongsTo(Office::class, 'procuring_office_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class)->withTrashed();
    }

    /** The PPMP project the approved quantity went to. */
    public function ppmpItem(): BelongsTo
    {
        return $this->belongsTo(PpmpItem::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
