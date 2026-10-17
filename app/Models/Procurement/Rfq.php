<?php

namespace App\Models\Procurement;

use App\Enums\Region;
use App\Enums\RfqStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Request for Quotation: lines of one or more submitted PRs / JRs, of one region and one
 * procurement mode, sent out together. Issuing it gives it its number.
 */
class Rfq extends Model
{
    public const MANAGE_PERMISSION = 'manage rfq';

    protected $fillable = [
        'uuid', 'rfq_no', 'series_year', 'series', 'region', 'procurement_mode_id', 'title', 'delivery_place',
        'delivery_period', 'price_validity', 'warranty', 'payment_terms', 'submission_deadline', 'remarks',
        'signatory_name', 'signatory_designation', 'canvassed_by_name', 'status', 'total_abc', 'issued_at', 'cancelled_at', 'cancel_reason',
        'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'region'              => Region::class,
            'status'              => RfqStatus::class,
            'submission_deadline' => 'datetime',
            'total_abc'           => 'decimal:2',
            'issued_at'           => 'datetime',
            'cancelled_at'        => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Rfq $rfq) => $rfq->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** e.g. "RFQ 2026-0003", or "RFQ (draft)". */
    public function title(): string
    {
        return 'RFQ ' . ($this->rfq_no ?? '(draft)');
    }

    public function isEditable(): bool
    {
        return $this->status === RfqStatus::Draft;
    }

    /** RFQs of the user's region (Super Admin: all). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole('Super Admin') ? $query : $query->where('region', $user->region ?? Region::Lm);
    }

    /** The PRs / JRs its lines come from, in number order. */
    public function requests(): Collection
    {
        return $this->items->map(fn (RfqItem $i) => $i->line?->request)->filter()->unique('id')
            ->sortBy(fn ($pr) => [$pr->series_year, $pr->series])->values();
    }

    public function items(): HasMany
    {
        return $this->hasMany(RfqItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function procurementMode(): BelongsTo
    {
        return $this->belongsTo(ProcurementMode::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
