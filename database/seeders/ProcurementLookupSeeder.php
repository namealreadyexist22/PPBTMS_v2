<?php

namespace Database\Seeders;

use App\Models\Procurement\BidDocument;
use App\Models\Procurement\ExpenseClass;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\Location;
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

        // MOOE objects of expenditure: the columns of the COB's Details of MOOE (Form B), in this order
        $order = 0;
        foreach ([
            'TRV'  => 'Traveling Expenses',
            'TRN'  => 'Training Expenses',
            'OFS'  => 'Office Supplies Expenses',
            'FUEL' => 'Fuel, Oil and Lubricants Expenses',
            'AGR'  => 'Agricultural Supplies Expense',
            ExpenseClass::SEMI_EXPENDABLE => 'Semi-Expendable Expenses',
            'OSE'  => 'Other Supplies Expenses',
            'GSV'  => 'General Services Expenses',
            'RPM'  => 'Repairs and Maintenance Expenses',
            'ASB'  => 'Assistance and Subsidies',
            'REP'  => 'Representation Expenses',
            'TDE'  => 'Transportation and Delivery Expenses',
            'MEC'  => 'Major Events and Conventions Expenses',
            'OTH'  => 'Other MOOE',
        ] as $code => $name) {
            ExpenseClass::firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => ($order += 10)]);
        }
        // Fixed Utilities' columns (before Other MOOE)
        foreach (['WAT' => ['Water Expenses', 132], 'ELEC' => ['Electricity Expenses', 134], 'COMM' => ['Communication Expenses', 136]] as $code => [$name, $sort]) {
            ExpenseClass::firstOrCreate(['code' => $code], ['name' => $name, 'sort_order' => $sort]);
        }

        $this->seed(Location::class, [
            'QC'  => 'Quezon City',
            'BCD' => 'Bacolod City',
            'LGJ' => 'La Granja',
        ]);

        // Expense class a category's MOOE projects start with (only where none is set yet)
        foreach (['OFS' => 'OFS', 'AGR' => 'AGR', 'SVC' => 'GSV'] as $category => $class) {
            ItemCategory::where('code', $category)->whereNull('expense_class_id')
                ->update(['expense_class_id' => ExpenseClass::where('code', $class)->value('id')]);
        }

        // Each bidder's checklist in a competitive bidding (RA 12009 eligibility, technical and financial documents)
        $this->seed(BidDocument::class, [
            'PHILGEPS'   => 'PhilGEPS Certificate of Registration (Platinum)',
            'ONGOING'    => 'Statement of all ongoing government and private contracts',
            'SLCC'       => 'Statement of the single largest completed contract (SLCC)',
            'NFCC'       => 'Net Financial Contracting Capacity (NFCC) or committed line of credit',
            'JVA'        => 'Joint Venture Agreement (if applicable)',
            'BIDSEC'     => 'Bid security',
            'OSS'        => 'Omnibus Sworn Statement',
            'TECHSPEC'   => 'Technical specifications / Schedule of requirements',
            'BIDFORM'    => 'Financial bid form',
            'PRICESCHED' => 'Price schedule',
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
