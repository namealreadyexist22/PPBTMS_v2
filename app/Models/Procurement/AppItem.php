<?php

namespace App\Models\Procurement;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * One APP line: a procurement project made of one or more similar PPMP projects,
 * possibly from several offices. Its budget is the sum of those PPMP projects.
 */
class AppItem extends Model
{
    protected $fillable = [
        'annual_procurement_plan_id', 'pap_code', 'pap_title', 'is_cse', 'project_title', 'end_user',
        'description', 'procurement_mode_id', 'early_procurement', 'bid_criteria', 'proc_start', 'proc_end',
        'fund_source_id', 'estimated_budget', 'procurement_strategy', 'remarks', 'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_cse'            => 'boolean',
            'early_procurement' => 'boolean',
            'proc_start'        => 'date',
            'proc_end'          => 'date',
            'estimated_budget'  => 'decimal:2',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(AnnualProcurementPlan::class, 'annual_procurement_plan_id');
    }

    public function ppmpItems(): BelongsToMany
    {
        return $this->belongsToMany(PpmpItem::class, 'app_item_ppmp_item')->withPivot('annual_procurement_plan_id');
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
