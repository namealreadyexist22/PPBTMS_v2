<?php

namespace App\Models\Procurement;

use App\Enums\AllotmentClass;
use App\Enums\ProjectType;
use App\Support\Money;
use App\Support\Quantity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PpmpItem extends Model
{
    protected $fillable = [
        'ppmp_id', 'ppmp_pap_id', 'line_uuid', 'item_id', 'description', 'project_type', 'quantity', 'unit_id', 'unit_cost',
        'quantity_size', 'procurement_mode_id', 'pre_proc_conference', 'is_epa', 'proc_start', 'proc_end',
        'delivery_period', 'fund_source_id', 'allotment_class', 'estimated_budget', 'committed_amount',
        'committed_quantity', 'supporting_documents', 'market_scoping', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'project_type'        => ProjectType::class,
            'allotment_class'     => AllotmentClass::class,
            'quantity'            => 'decimal:2',
            'unit_cost'           => 'decimal:2',
            'pre_proc_conference' => 'boolean',
            'is_epa'              => 'boolean',
            'market_scoping'      => 'array',
            'proc_start'          => 'date',
            'proc_end'            => 'date',
            'estimated_budget'    => 'decimal:2',
            'committed_amount'    => 'decimal:2',
            'committed_quantity'  => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PpmpItem $item) {
            $item->line_uuid ??= (string) Str::uuid();
        });
    }

    /** Budget still available for new PR charges. */
    public function availableBudget(): string
    {
        return Money::fromCents(Money::toCents($this->estimated_budget) - Money::toCents($this->committed_amount));
    }

    /**
     * Planned quantity in hundredths of a unit; null when it sets no limit: a lot (no quantity)
     * or a quantity of 0 (budget entered directly).
     */
    public function plannedHundredths(): ?int
    {
        $planned = Quantity::toHundredths($this->quantity);

        return $planned > 0 ? $planned : null;
    }

    /** Quantity charged by submitted requests, in hundredths of a unit. */
    public function committedHundredths(): int
    {
        return Quantity::toHundredths($this->committed_quantity);
    }

    /** Loaded once per instance by appMode(); false = looked up, none. */
    protected ProcurementMode|false|null $appModeCache = null;

    /**
     * The mode of procurement the BAC set for this project in the approved APP (the APP line that
     * holds it, followed across PPMP amendments by line_uuid), or null when no approved APP has it.
     * The BAC has the last say on the mode: PRs, JRs and RFQs follow this over the PPMP's own.
     */
    public function appMode(): ?ProcurementMode
    {
        if ($this->appModeCache === null) {
            $this->appModeCache = AppItem::whereHas('ppmpItems', fn ($q) => $q->where('line_uuid', $this->line_uuid))
                ->whereHas('plan', fn ($q) => $q->where('status', \App\Enums\AppStatus::Approved))
                ->with('procurementMode')->latest('id')->first()?->procurementMode ?? false;
        }

        return $this->appModeCache ?: null;
    }

    /** The mode that applies: the BAC's in the approved APP, else the PPMP's. */
    public function effectiveMode(): ?ProcurementMode
    {
        return $this->appMode() ?? $this->procurementMode;
    }

    public function effectiveModeId(): ?int
    {
        return ($id = $this->effectiveMode()?->id) ? (int) $id : null;
    }

    /** Quantity x unit cost in centavos, or null when either is missing (lot budgets). */
    public static function computedBudgetCents($quantity, $unitCost): ?int
    {
        if ($quantity === null || $quantity === '' || $unitCost === null || $unitCost === '') {
            return null;
        }

        return (int) round(((float) $quantity) * Money::toCents($unitCost));
    }

    public function ppmp(): BelongsTo
    {
        return $this->belongsTo(Ppmp::class);
    }

    public function pap(): BelongsTo
    {
        return $this->belongsTo(PpmpPap::class, 'ppmp_pap_id');
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    public function procurementMode(): BelongsTo
    {
        return $this->belongsTo(ProcurementMode::class);
    }

    public function fundSource(): BelongsTo
    {
        return $this->belongsTo(FundSource::class);
    }

    public function attachments(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PpmpItemAttachment::class)->orderBy('id');
    }

    /** Market scoping is done when the period, at least one activity and every parameter are filled. */
    public function marketScopingComplete(): bool
    {
        $ms = $this->market_scoping ?? [];
        $parameters = array_keys(config('market_scoping.parameters'));

        return ! empty($ms['period_from']) && ! empty($ms['period_to'])
            && (! empty($ms['activities']) || ! empty($ms['activity_other']))
            && collect($parameters)->every(fn ($key) => ! empty($ms['parameters'][$key]['answer']));
    }

    /** Which offices get this project's items (the procuring unit's assessment). */
    public function distributions(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(PpmpItemDistribution::class, 'line_uuid', 'line_uuid')->orderBy('id');
    }
}
