<?php

namespace Tests\Feature\Procurement;

use App\Enums\PpmpStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Item;
use App\Models\Procurement\Office;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StandardItemTest extends TestCase
{
    use RefreshDatabase;

    protected User $twg;
    protected User $staff;
    protected Office $mis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);

        $this->twg = User::factory()->create(['is_activated' => 1]);
        $this->twg->givePermissionTo('manage items');
        $head = User::factory()->create();
        $division = Office::create(['code' => '05010', 'name' => 'PPPD', 'head_user_id' => $head->id, 'is_consolidating' => true]);
        $this->mis = Office::create(['code' => '05012', 'name' => 'MIS', 'parent_id' => $division->id]);
        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->mis->id]);
        $this->staff->givePermissionTo('manage ppmp');
    }

    protected function line(array $overrides = []): array
    {
        return array_merge([
            'description' => 'Procurement of TV for the conference room', 'project_type' => 'goods', 'allotment_class' => 'co',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'proc_start' => '2027-02-01', 'proc_end' => '2027-03-01',
        ], $overrides);
    }

    public function test_twg_manages_standard_items_on_their_page(): void
    {
        $unit = Unit::first();
        $this->actingAs($this->twg)->get(route('procurement.items.index'))->assertOk()->assertSee('No standard items yet');

        $ict = \App\Models\Procurement\ItemCategory::where('code', 'ICT')->value('id');
        $this->postJson(route('procurement.items.store'), [
            'code' => 'ignored', 'name' => 'LED Smart TV, 55"', 'item_category_id' => $ict, 'unit_id' => $unit->id, 'project_type' => 'goods',
            'standard_unit_cost' => '50,000.00', 'specifications' => '55-inch 4K UHD, 3 HDMI', 'twg_reference' => 'TWG-ICT Res. 2026-03', 'is_active' => 1,
        ])->assertOk()->assertJsonPath('message', 'Added ICT-0001 "LED Smart TV, 55"".');

        // The series continues per category; no category = ITEM-
        $this->postJson(route('procurement.items.store'), ['name' => 'Laptop', 'item_category_id' => $ict, 'unit_id' => $unit->id, 'project_type' => 'goods'])->assertOk();
        $this->postJson(route('procurement.items.store'), ['name' => 'Bond paper', 'unit_id' => $unit->id, 'project_type' => 'goods'])->assertOk();
        $this->assertSame(['ICT-0001', 'ICT-0002', 'ITEM-0001'], Item::orderBy('id')->pluck('code')->all());
        $this->get(route('procurement.items.entry'))->assertOk()->assertSee('ITEM-0002');

        $this->get(route('procurement.items.index'))->assertOk()->assertSee('ICT-0001')->assertSee('₱50,000.00')->assertSee('TWG-ICT Res. 2026-03');

        // Editing keeps the code; it can be corrected but must stay unique
        $tv = Item::where('code', 'ICT-0001')->sole();
        $this->postJson(route('procurement.items.store'), ['id' => $tv->id, 'code' => 'ICT-0002', 'name' => $tv->name, 'unit_id' => $unit->id, 'project_type' => 'goods'])
            ->assertStatus(422)->assertJsonValidationErrors('code');

        // Deactivate (unticked box sends 0)
        $this->postJson(route('procurement.items.store'), ['id' => $tv->id, 'code' => 'ICT-0001', 'name' => $tv->name, 'item_category_id' => $ict, 'unit_id' => $unit->id, 'project_type' => 'goods', 'standard_unit_cost' => '50000', 'is_active' => 0])->assertOk();
        $this->assertFalse($tv->fresh()->is_active);
        $this->assertSame('ICT-0001', $tv->fresh()->code);

        // Others cannot manage the catalog
        $this->actingAs($this->staff)->get(route('procurement.items.index'))->assertForbidden();
    }

    public function test_project_on_a_standard_item_takes_its_price_specs_and_unit(): void
    {
        $unit = Unit::first();
        $other = Unit::skip(1)->first() ?? $unit;
        $tv = Item::create(['code' => 'ICT-TV-55', 'name' => 'LED Smart TV, 55"', 'unit_id' => $unit->id, 'project_type' => 'goods',
            'standard_unit_cost' => '50000', 'specifications' => '55-inch 4K UHD, 3 HDMI', 'is_active' => true]);

        $service = app(PpmpService::class);
        $ppmp = $service->create($this->mis, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');

        // Tampered price, specs and unit are replaced by the catalog's; budget = qty x standard cost
        $item = $service->addItem($ppmp, $this->line(['ppmp_pap_id' => $pap->id, 'item_id' => $tv->id, 'quantity' => 2,
            'unit_id' => $other->id, 'unit_cost' => '30000', 'quantity_size' => 'any 55" TV', 'estimated_budget' => '60000']));

        $this->actingAs($this->staff)->get(route('procurement.ppmp.items.entry', $ppmp))->assertOk()
            ->assertSee('ICT-TV-55 — LED Smart TV, 55&quot; (₱50,000.00', false)->assertSee('"cost":50000', false)->assertSee('"specs":"55-inch 4K UHD, 3 HDMI"', false);
        $this->assertEquals('50000.00', $item->unit_cost);
        $this->assertEquals('100000.00', $item->estimated_budget);
        $this->assertSame('55-inch 4K UHD, 3 HDMI', $item->quantity_size);
        $this->assertSame($unit->id, $item->unit_id);

        // Quantity is required for a standard item
        try {
            $service->addItem($ppmp, $this->line(['ppmp_pap_id' => $pap->id, 'item_id' => $tv->id, 'estimated_budget' => '50000']));
            $this->fail('Expected a ProcurementException.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString('Enter the quantity', $e->getMessage());
        }

        // A later price change applies when the project is re-saved; a deactivated item stays on its projects but cannot be newly picked
        $tv->update(['standard_unit_cost' => '55000', 'is_active' => false]);
        $service->updateItem($item->fresh(), ['quantity' => 3]);
        $this->assertEquals('165000.00', $item->fresh()->estimated_budget);
        $this->expectException(ProcurementException::class);
        $service->addItem($ppmp, $this->line(['ppmp_pap_id' => $pap->id, 'item_id' => $tv->id, 'quantity' => 1, 'estimated_budget' => '55000']));
    }

    public function test_epa_flag_shows_on_the_ppmp_and_goes_to_the_app_line(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->mis, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');

        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.items.store', $ppmp), array_merge($this->line(['ppmp_pap_id' => $pap->id, 'estimated_budget' => '80,000']), [
            'proc_start' => '2027-01', 'proc_end' => '2027-02', 'is_epa' => 1,
        ]))->assertOk();
        $item = $ppmp->items()->sole();
        $this->assertTrue($item->is_epa);

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('>EPA<', false);
        $this->get(route('procurement.ppmp.print', $ppmp))->assertOk()->assertSee('Early Procurement Activity');

        // Approved into the APP: the line is an early procurement activity
        $service->submit($ppmp->fresh(), $this->staff);
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->mis->parent, 2027, \App\Enums\Region::Lm, $this->mis->parent->head, $this->mis->parent->head);
        $apps = app(\App\Services\Procurement\AppService::class);
        $app = $apps->create(2027, \App\Enums\Region::Lm, $this->staff);
        $apps->generateLines($app);
        $this->assertTrue($app->items()->sole()->early_procurement);

        // An amendment keeps the flag
        $this->assertSame(PpmpStatus::Approved, $ppmp->fresh()->status);
        $this->assertTrue($service->amend($ppmp->fresh(), $this->staff)->items()->sole()->is_epa);
    }

    public function test_ict_equipment_only_by_mis_for_cob_but_sida_departments_may(): void
    {
        $unit = Unit::first();
        $ict = \App\Models\Procurement\ItemCategory::updateOrCreate(['code' => 'ICT-EQ'], ['name' => 'ICT Equipment', 'restricted_office_id' => $this->mis->id, 'restricted_fund_group' => 'regular', 'is_active' => true]);
        $laptop = Item::create(['code' => 'ICT-LAP', 'name' => 'Laptop', 'unit_id' => $unit->id, 'item_category_id' => $ict->id, 'project_type' => 'goods', 'standard_unit_cost' => '65000', 'is_active' => true]);
        $this->assertSame('Procured by 05012 only (COB)', $ict->restrictionLabel());

        $service = app(PpmpService::class);
        $line = fn (Office $office) => $this->line(['item_id' => $laptop->id, 'quantity' => 1, 'estimated_budget' => '65000']);

        // MIS can
        $misPpmp = $service->create($this->mis, 2027, $this->staff);
        $pap = $service->addPap($misPpmp, $service->suggestPapCode($misPpmp), 'ICT');
        $service->addItem($misPpmp, $line($this->mis) + ['ppmp_pap_id' => $pap->id]);

        // Legal (COB) cannot: hidden in its form and refused on save
        $legal = Office::create(['code' => '04000', 'name' => 'LEGAL']);
        $lawyer = User::factory()->create(['is_activated' => 1, 'office_id' => $legal->id]);
        $lawyer->givePermissionTo('manage ppmp');
        $legalPpmp = $service->create($legal, 2027, $lawyer);
        $legalPap = $service->addPap($legalPpmp, $service->suggestPapCode($legalPpmp), 'Admin');
        $this->actingAs($lawyer)->get(route('procurement.ppmp.items.entry', $legalPpmp))->assertOk()->assertDontSee('ICT-LAP');
        try {
            $service->addItem($legalPpmp, $line($legal) + ['ppmp_pap_id' => $legalPap->id]);
            $this->fail('Expected a ProcurementException.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString('Procured by 05012 only (COB)', $e->getMessage());
        }

        // A SIDA department buying with SIDA may
        $sidaDept = Office::create(['code' => '11000', 'acronym' => 'SIDA-SCP', 'name' => 'SIDA-SCP', 'type' => 'department', 'budget_fund' => 'sida']);
        $sidaStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $sidaDept->id]);
        $sidaStaff->givePermissionTo('manage ppmp');
        $sidaPpmp = $service->create($sidaDept, 2027, $sidaStaff);
        $sidaPap = $service->addPap($sidaPpmp, $service->suggestPapCode($sidaPpmp), 'SCP');
        $this->actingAs($sidaStaff)->get(route('procurement.ppmp.items.entry', $sidaPpmp))->assertOk()->assertSee('ICT-LAP');
        $service->addItem($sidaPpmp, array_merge($line($sidaDept), ['ppmp_pap_id' => $sidaPap->id, 'fund_source_id' => FundSource::where('code', 'SIDA')->value('id')]));
        $this->assertSame(1, $sidaPpmp->items()->count());
    }

    public function test_mis_keeps_a_distribution_list_that_follows_the_project(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->mis, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');
        $item = $service->addItem($ppmp, $this->line(['ppmp_pap_id' => $pap->id, 'quantity' => 12, 'unit_cost' => '65000', 'estimated_budget' => '780000']));
        $legal = Office::create(['code' => '04000', 'name' => 'LEGAL']);
        $url = route('procurement.ppmp.items.distribution.store', [$ppmp, $item]);

        $this->actingAs($this->staff)->get(route('procurement.ppmp.items.distribution', [$ppmp, $item]))->assertOk()->assertSee('Add office');
        $this->postJson($url, ['rows' => [['office_id' => $legal->id, 'quantity' => 10], ['office_id' => $this->mis->id, 'quantity' => 3]]])
            ->assertStatus(422)->assertJson(['message' => 'The distribution (13) is more than the project quantity (12).']);
        $this->postJson($url, ['rows' => [['office_id' => $legal->id, 'quantity' => 4, 'recipient' => 'Atty. Reyes'], ['office_id' => $this->mis->id, 'quantity' => 8]]])->assertOk();
        $this->assertEquals(12, $item->distributions()->sum('quantity'));

        // Not on the PPMP print; shown as a count on the page
        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('Distribution: 2 offices');
        $this->get(route('procurement.ppmp.print', $ppmp))->assertDontSee('Atty. Reyes');

        // Still editable after approval; follows the project into an amendment
        $service->submit($ppmp->fresh(), $this->staff);
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->mis->parent, 2027, \App\Enums\Region::Lm, $this->mis->parent->head, $this->mis->parent->head);
        $this->postJson($url, ['rows' => [['office_id' => $legal->id, 'quantity' => 5]]])->assertOk();
        $v2 = $service->amend($ppmp->fresh(), $this->staff);
        $this->assertSame(['5.00'], $v2->items()->sole()->distributions()->pluck('quantity')->map(fn ($q) => (string) $q)->all());

        // Another office can view but not change it
        $other = User::factory()->create(['is_activated' => 1]);
        $other->givePermissionTo(['manage ppmp', \Spatie\Permission\Models\Permission::findOrCreate('menu.ppmp-view-all')]);
        $this->actingAs($other)->get(route('procurement.ppmp.items.distribution', [$ppmp, $item]))->assertOk()->assertDontSee('Add office');
        $this->postJson($url, ['rows' => []])->assertForbidden();
    }

    public function test_semi_expendable_class_and_source_of_funds_picked_for_the_office(): void
    {
        $legal = Office::create(['code' => '04000', 'acronym' => 'LEGAL', 'name' => 'LEGAL DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular']);
        $lawyer = User::factory()->create(['is_activated' => 1, 'office_id' => $legal->id]);
        $lawyer->givePermissionTo('manage ppmp');
        $service = app(PpmpService::class);
        $ppmp = $service->create($legal, 2027, $lawyer);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'Admin');
        $cob = FundSource::where('code', 'COB')->value('id');

        // A COB department has one fund source to choose from: it comes selected, with no "Choose..."
        $form = $this->actingAs($lawyer)->get(route('procurement.ppmp.items.entry', $ppmp))->assertOk()
            ->assertSee('value="' . $cob . '" data-group="regular" selected', false)->assertSee('Semi-Expendable')->getContent();
        preg_match('/<select name="fund_source_id".*?<\/select>/s', $form, $fundSelect);
        $this->assertStringNotContainsString('Choose...', $fundSelect[0]);

        $this->postJson(route('procurement.ppmp.items.store', $ppmp), array_merge($this->line(['ppmp_pap_id' => $pap->id, 'fund_source_id' => $cob, 'allotment_class' => 'semi', 'estimated_budget' => '30,000']), ['proc_start' => '2027-01', 'proc_end' => '2027-02']))->assertOk();
        $this->assertSame(\App\Enums\AllotmentClass::Semi, $ppmp->items()->sole()->allotment_class);
        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('Semi-Exp.');
    }

    public function test_distribution_lists_offices_of_the_ppmp_region_by_department(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->mis, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');
        $item = $service->addItem($ppmp, $this->line(['ppmp_pap_id' => $pap->id, 'quantity' => 5, 'unit_cost' => '65000', 'estimated_budget' => '325000']));

        $oda = Office::create(['type' => 'department', 'code' => '06000', 'acronym' => 'ODA-AF', 'name' => 'OFC OF THE DEP. ADMIN']);
        $afdLm = Office::create(['type' => 'department', 'acronym' => 'AFD-LM', 'name' => 'AFD-LUZON MINDANAO', 'parent_id' => $oda->id, 'region' => 'lm']);
        $gad = Office::create(['type' => 'division', 'code' => '06020', 'name' => 'AFD-LM GENERAL ADMINISTRATIVE DIVISION', 'parent_id' => $afdLm->id]);
        $afdVis = Office::create(['type' => 'department', 'acronym' => 'AFD-VIS', 'name' => 'AFD-VISAYAS', 'parent_id' => $oda->id, 'region' => 'vis']);
        $visGad = Office::create(['type' => 'division', 'code' => '06560', 'name' => 'AFD-VISAYAS - GENERAL ADMINISTRATIVE DIVISION', 'parent_id' => $afdVis->id]);

        $this->assertSame(\App\Enums\Region::Vis, $visGad->effectiveRegion());
        $this->assertSame(\App\Enums\Region::Lm, $gad->effectiveRegion());

        $this->actingAs($this->staff)->get(route('procurement.ppmp.items.distribution', [$ppmp, $item]))->assertOk()
            ->assertSee('AFD-LM \\u2014 AFD-LUZON MINDANAO', false)
            ->assertSee('AFD-LM GENERAL ADMINISTRATIVE DIVISION')
            ->assertDontSee('AFD-VISAYAS');

        $url = route('procurement.ppmp.items.distribution.store', [$ppmp, $item]);
        $this->postJson($url, ['rows' => [['office_id' => $visGad->id, 'quantity' => 1]]])
            ->assertStatus(422)->assertJson(['message' => 'Only LM offices can receive items of this PPMP.']);
        $this->postJson($url, ['rows' => [['office_id' => $gad->id, 'quantity' => 2]]])->assertOk();
    }
}
