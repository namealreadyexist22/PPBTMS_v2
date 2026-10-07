<?php

namespace App\Models\Procurement;

use App\Enums\AllotmentClass;
use App\Enums\ProjectType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PpmpItem extends Model
{
    protected $fillable = [
        'ppmp_id', 'ppmp_pap_id', 'line_uuid', 'item_id', 'description', 'project_type', 'quantity', 'unit_id', 'unit_cost',
        'quantity_size', 'procurement_mode_id', 'pre_proc_conference', 'is_epa', 'proc_start', 'proc_end',
        'delivery_period', 'fund_source_id', 'allotment_class', 'estimated_budget', 'committed_amount',
        'supporting_documents', 'market_scoping', 'remarks', 'sort_order',
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
