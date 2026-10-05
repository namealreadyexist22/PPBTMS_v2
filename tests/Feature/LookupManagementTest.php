<?php

namespace Tests\Feature;

use App\Models\Procurement\FundSource;
use App\Models\User;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LookupManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        $user = User::factory()->create(['is_activated' => 1]);
        $user->givePermissionTo('manage lookups');
        $this->actingAs($user);
    }

    public function test_add_edit_deactivate_and_delete_fund_sources(): void
    {
        $this->get(route('core.lookups.index'))->assertOk()->assertSee('Fund Sources')->assertSee('Corporate Operating Budget');

        $this->postJson(route('core.lookups.store'), ['type' => 'fund-sources', 'code' => 'GAA2017-CA', 'name' => 'GAA 2017 - Continuing Appropriation', 'fund_group' => 'sida'])->assertOk();
        $this->postJson(route('core.lookups.store'), ['type' => 'fund-sources', 'code' => 'GAA2017-CA', 'name' => 'Duplicate', 'fund_group' => 'regular'])->assertStatus(422)->assertJsonValidationErrors('code');
        $gaa = FundSource::where('code', 'GAA2017-CA')->sole();
        $this->assertTrue($gaa->is_active);
        $this->assertSame(\App\Enums\FundGroup::Sida, $gaa->fund_group);
        $this->get(route('core.lookups.index'))->assertSee('SIDA</span>', false);

        $this->postJson(route('core.lookups.store'), ['type' => 'fund-sources', 'id' => $gaa->id, 'code' => 'GAA2017-CA', 'name' => 'GAA 2017 - Continuing Appropriation', 'fund_group' => 'sida', 'is_active' => 0])->assertOk();
        $this->assertFalse($gaa->fresh()->is_active);

        $this->deleteJson(route('core.lookups.destroy'), ['type' => 'fund-sources', 'id' => $gaa->id])->assertOk();
        $this->assertNull(FundSource::find($gaa->id));

        $this->get(route('core.lookups.index', ['type' => 'units']))->assertOk()->assertSee('piece');
        $this->get(route('core.lookups.index', ['type' => 'nope']))->assertNotFound();
    }

    public function test_used_entries_cannot_be_deleted(): void
    {
        $cob = FundSource::where('code', 'COB')->sole();

        $office = \App\Models\Procurement\Office::create(['code' => '09000', 'name' => 'GAD']);
        $staff = User::factory()->create(['office_id' => $office->id]);
        $service = app(\App\Services\Procurement\PpmpService::class);
        $ppmp = $service->create($office, 2027, $staff);
        $pap = $service->addPap($ppmp, '27-09000-01', 'GAD');
        $service->addItem($ppmp, ['ppmp_pap_id' => $pap->id, 'description' => 'Kits', 'project_type' => 'goods',
            'procurement_mode_id' => \App\Models\Procurement\ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id' => $cob->id, 'proc_start' => '2027-01-01', 'proc_end' => '2027-01-01', 'estimated_budget' => '100']);

        $this->deleteJson(route('core.lookups.destroy'), ['type' => 'fund-sources', 'id' => $cob->id])->assertStatus(422)->assertJson(['status' => 'error']);
        $this->assertNotNull($cob->fresh());
    }
}
