<?php

namespace App\Models\Procurement;

use App\Enums\ProjectType;
use App\Support\Money;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class PpmpItem extends Model
{
    protected $fillable = [
        'ppmp_id', 'ppmp_pap_id', 'line_uuid', 'item_id', 'description', 'project_type', 'quantity', 'unit_id',
        'quantity_size', 'procurement_mode_id', 'pre_proc_conference', 'proc_start', 'proc_end',
        'delivery_period', 'fund_source_id', 'estimated_budget', 'committed_amount',
        'supporting_documents', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'project_type'        => ProjectType::class,
            'quantity'            => 'decimal:2',
            'pre_proc_conference' => 'boolean',
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
}
