<?php

namespace Database\Seeders;

use App\Models\Procurement\FundSource;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use Illuminate\Database\Seeder;

/**
 * Starter lookup values. Safe to re-run: only missing codes are added. Manage them
 * afterwards in Settings > Procurement Lookups.
 */
class ProcurementLookupSeeder extends Seeder
{
    public function run(): void
    {
        $this->seed(ProcurementMode::class, [
            'CB'   => 'Competitive Bidding',
            'LSB'  => 'Limited Source Bidding',
            'DC'   => 'Direct Contracting',
            'RO'   => 'Repeat Order',
            'SVP'  => 'Small Value Procurement',
            'SHP'  => 'Shopping',
            'NEG'  => 'Negotiated Procurement',
            'EMP'  => 'Emergency Procurement',
            'AA'   => 'Agency-to-Agency',
            'DAS'  => 'Direct Acquisition',
            'DSO'  => 'Direct Sales',
            'PS'   => 'Procurement Service (PS-DBM)',
        ]);

        // Starter fund sources; add more (e.g. GAA 2017 - Continuing Appropriation)
        // in Settings > Procurement Lookups. Re-seeding never disables those.
        $this->seed(FundSource::class, [
            'COB'  => 'Corporate Operating Budget',
            'SIDA' => ['name' => 'Sugar Industry Development Act', 'fund_group' => 'sida'],   // own APP
        ]);

        $this->seed(Unit::class, [
            'pc'    => 'piece',
            'unit'  => 'unit',
            'set'   => 'set',
            'lot'   => 'lot',
            'box'   => 'box',
            'ream'  => 'ream',
            'pack'  => 'pack',
            'bot'   => 'bottle',
            'kg'    => 'kilogram',
            'l'     => 'liter',
            'job'   => 'job',
        ]);

        $this->seed(ItemCategory::class, [
            'OFS'  => 'Office Supplies',
            'ICT'  => 'ICT Equipment and Supplies',
            'AGR'  => 'Agricultural Supplies',
            'FUR'  => 'Furniture and Fixtures',
            'SVC'  => 'Services',
            'INF'  => 'Infrastructure',
        ]);
    }

    /** Adds missing codes only; names and active flags edited in Settings are left alone. */
    protected function seed(string $model, array $rows): void
    {
        foreach ($rows as $code => $values) {
            $model::firstOrCreate(['code' => $code], is_array($values) ? $values : ['name' => $values]);
        }
    }
}
