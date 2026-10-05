<?php

namespace Tests\Feature\Procurement;

use App\Models\Procurement\FundSource;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementLookupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_only_adds_missing_codes(): void
    {
        $this->seed(ProcurementLookupSeeder::class);

        // Managed in Settings afterwards: renamed, added, deactivated
        FundSource::where('code', 'COB')->update(['name' => 'Corporate Operating Budget (COB)']);
        FundSource::create(['code' => 'GAA2017-CA', 'name' => 'GAA 2017 - Continuing Appropriation']);
        FundSource::where('code', 'SIDA')->update(['is_active' => false]);

        $this->seed(ProcurementLookupSeeder::class);   // safe to re-run

        $this->assertSame('Corporate Operating Budget (COB)', FundSource::where('code', 'COB')->value('name'));
        $this->assertTrue(FundSource::where('code', 'GAA2017-CA')->value('is_active'));
        $this->assertFalse(FundSource::where('code', 'SIDA')->value('is_active'));
    }
}
