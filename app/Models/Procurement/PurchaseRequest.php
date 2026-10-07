<?php

namespace App\Models\Procurement;

use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * A Purchase Request (goods) or Job Request (services), charged to one PAP of an approved PPMP.
 * Submitting it gives it its number and charges its lines to the PPMP; a revision keeps the
 * number and the signatories and replaces the submitted request when it is submitted.
 */
class PurchaseRequest extends Model
{
    protected $fillable = [
        'uuid', 'kind', 'request_no', 'series_year', 'series', 'revision', 'revised_from_id', 'fiscal_year', 'office_id',
        'ppmp_id', 'ppmp_pap_id', 'jr_type', 'purpose', 'sai_no', 'sai_date', 'status', 'total_amount',
        'requested_by_name', 'requested_by_designation', 'approved_by_name', 'approved_by_designation',
        'submitted_at', 'cancelled_at', 'cancel_reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'kind'         => RequestKind::class,
            'status'       => RequestStatus::class,
            'sai_date'     => 'date',
            'total_amount' => 'decimal:2',
            'submitted_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (PurchaseRequest $request) => $request->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** e.g. "PR 2026-06-1218 Rev. 1", or "PR (draft)" before it is submitted. */
    public function title(): string
    {
        return $this->kind->short() . ' ' . ($this->request_no ?? '(draft)') . ($this->revision ? " Rev. {$this->revision}" : '');
    }

    public function isEditable(): bool
    {
        return $this->status === RequestStatus::Draft;
    }

    /** "FOR BIDDING" when a line's mode is competitive bidding, else "REGULAR PROCUREMENT". */
    public function procurementLabel(): string
    {
        $codes = config('procurement.bidding_mode_codes', ['CB']);

        return $this->items->contains(fn ($line) => in_array($line->ppmpItem?->procurementMode?->code, $codes, true))
            ? 'FOR BIDDING' : 'REGULAR PROCUREMENT';
    }

    /** Requests a user may see: their home and PR offices', those under offices they head, or all with view-all. */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        if ($user->canAccessPermission(Ppmp::VIEW_ALL_PERMISSION)) {
            return $query;
        }

        $headed = Office::where('head_user_id', $user->id)->pluck('id');

        return $query->whereIn('office_id', Office::withDescendantIds($headed)->merge($user->prOfficeIds())->unique());
    }

    /** Home office and additional PR offices may prepare requests for the office. */
    public function isEditableBy(User $user): bool
    {
        return $user->hasRole('Super Admin') || in_array((int) $this->office_id, $user->prOfficeIds(), true);
    }

    public function items(): HasMany
    {
        return $this->hasMany(PurchaseRequestItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function office(): BelongsTo
    {
        return $this->belongsTo(Office::class);
    }

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }

    public function pap(): BelongsTo
    {
        return $this->belongsTo(PpmpPap::class, 'ppmp_pap_id');
    }

    public function revisedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'revised_from_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function recalculateTotal(): void
    {
        $this->update(['total_amount' => \App\Support\Money::fromCents($this->items()->get()->sum(fn ($i) => \App\Support\Money::toCents($i->total_cost)))]);
    }
}
