<?php

namespace Database\Seeders;

use App\Models\Procurement\FundSource;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use Illuminate\Database\Seeder;

/**
 * Starter lookup values. Safe to re-run; edit names/codes to match your office.
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

        $this->seed(FundSource::class, [
            'GAA'   => 'General Appropriations Act',
            'CONT'  => 'Continuing Appropriations',
            'TF'    => 'Trust Fund',
            'SAA'   => 'Sub-Allotment',
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

    protected function seed(string $model, array $rows): void
    {
        foreach ($rows as $code => $name) {
            $model::firstOrCreate(['code' => $code], ['name' => $name]);
        }
    }
}
