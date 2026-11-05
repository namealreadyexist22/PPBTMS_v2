<?php

namespace App\Models\Procurement;

use App\Enums\BiddingStatus;
use App\Enums\BiddingStep;
use App\Enums\Region;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Competitive (public) bidding: Competitive Bidding lines of received PRs / JRs of one region and one
 * fund source, in lots awarded on their own. Numbered when the BAC creates it (YYYY-FUND-NNNN, series per
 * year and fund); its steps then follow the agency's process (see BiddingStep).
 */
class Bidding extends Model
{
    use Concerns\HasSeriesYear;

    public const MANAGE_PERMISSION = 'manage bidding';

    protected $fillable = [
        'uuid', 'bid_no', 'series_year', 'series', 'fund_source_id', 'region', 'title', 'status', 'stage', 'total_abc',
        'preproc_at', 'preproc_held_at', 'advertised_at', 'philgeps_link', 'website_link',
        'prebid_at', 'prebid_held_at', 'bidding_at', 'bidding_resets', 'bidding_held_at',
        'cancelled_at', 'cancel_reason', 'created_by', 'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'region'          => Region::class,
            'status'          => BiddingStatus::class,
            'stage'           => BiddingStep::class,
            'total_abc'       => 'decimal:2',
            'preproc_at'      => 'datetime',
            'preproc_held_at' => 'date',
            'advertised_at'   => 'date',
            'prebid_at'       => 'datetime',
            'prebid_held_at'  => 'date',
            'bidding_at'      => 'datetime',
            'bidding_held_at' => 'date',
            'cancelled_at'    => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(fn (Bidding $bidding) => $bidding->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    /** e.g. "Bidding 2026-SIDA-0003". */
    public function title(): string
    {
        return 'Bidding ' . $this->bid_no;
    }

    /** Its title, or (none given) its items: "Desktop Computer and 2 more". */
    public function label(): string
    {
        if (filled($this->title)) {
            return $this->title;
        }

        $names = $this->lines()->pluck('description')->filter()->values();

        return match ($names->count()) {
            0       => 'No items yet',
            1       => $names[0],
            default => $names[0] . ' and ' . ($names->count() - 1) . ' more',
        };
    }

    /** Lots and their lines can change until the bidding is advertised. */
    public function lotsEditable(): bool
    {
        return $this->status === BiddingStatus::Ongoing && (! $this->stage || $this->stage->number() < BiddingStep::Advertised->number());
    }

    /** Biddings of the user's region (Super Admin: all). */
    public function scopeVisibleTo(Builder $query, User $user): Builder
    {
        return $user->hasRole('Super Admin') ? $query : $query->where('region', $user->region ?? Region::Lm);
    }

    /** Its PR / JR lines, lot by lot. */
    public function lines(): Collection
    {
        return $this->lots->flatMap(fn (BiddingLot $lot) => $lot->items->map->line)->filter()->values();
    }

    /** The PRs / JRs its lines come from, in number order. */
    public function requests(): Collection
    {
        return $this->lines()->map->request->filter()->unique('id')->sortBy(fn ($pr) => [$pr->series_year, $pr->series])->values();
    }

    public function lots(): HasMany
    {
        return $this->hasMany(BiddingLot::class)->orderBy('lot_no');
    }

    public function lotItems(): HasManyThrough
    {
        return $this->hasManyThrough(BiddingLotItem::class, BiddingLot::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(BiddingEvent::class)->orderBy('created_at')->orderBy('id');
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
