<?php

namespace Tests\Feature\Procurement;

use App\Models\Procurement\FundSource;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcurementLookupSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_reseeding_replaces_old_fund_sources_without_deleting_them(): void
    {
        FundSource::create(['code' => 'GAA', 'name' => 'General Appropriations Act']);

        $this->seed(ProcurementLookupSeeder::class);
        $this->seed(ProcurementLookupSeeder::class);   // safe to re-run

        $this->assertSame(['COB', 'SIDA'], FundSource::active()->orderBy('code')->pluck('code')->all());
        $this->assertFalse(FundSource::where('code', 'GAA')->first()->is_active);
        $this->assertSame('Corporate Operating Budget', FundSource::where('code', 'COB')->value('name'));
    }
}
