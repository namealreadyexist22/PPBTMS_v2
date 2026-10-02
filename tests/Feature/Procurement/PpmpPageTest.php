<?php

namespace Tests\Feature\Procurement;

use App\Enums\PpmpStatus;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PPMP screens end to end: a section builds its PPMP (PAPs + projects) and submits;
 * the division head returns or approves it on the Division PPMP page (PPMP No. 1);
 * an amendment approved later becomes PPMP No. 2.
 */
class PpmpPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $staff2;
    protected User $head;
    protected User $oic;
    protected User $outsider;
    protected Office $division;
    protected Office $section;
    protected Office $section2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);

        $this->head = User::factory()->create(['is_activated' => 1, 'fname' => 'Digna', 'minitial' => 'D', 'lname' => 'Gonzales', 'designation' => 'Manager III, PPSPD']);
        $this->division = Office::create(['code' => '05010', 'name' => 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'head_user_id' => $this->head->id, 'is_consolidating' => true]);
        $this->section = Office::create(['code' => '05012', 'name' => 'MIS SECTION', 'parent_id' => $this->division->id]);
        $this->section2 = Office::create(['code' => '05011', 'name' => 'PLANNING AND POLICY RESEARCH SECTION', 'parent_id' => $this->division->id]);
        $other = Office::create(['code' => '09000', 'name' => 'GAD']);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section->id]);
        $this->staff2 = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section2->id]);
        $this->oic = User::factory()->create(['is_activated' => 1, 'office_id' => $this->division->id, 'fname' => 'Anne', 'minitial' => null, 'lname' => 'Dilay', 'designation' => 'OIC, Planning, Policy and Programming Division']);
        $this->outsider = User::factory()->create(['is_activated' => 1, 'office_id' => $other->id]);

        foreach ([$this->staff, $this->staff2, $this->head, $this->oic, $this->outsider] as $user) {
            $user->givePermissionTo('manage ppmp');
        }
    }

    protected function project(int $papId, array $overrides = []): array
    {
        return array_merge([
            'ppmp_pap_id'         => $papId,
            'description'         => 'Supply and Delivery of Hard Drive (4 TB)',
            'project_type'        => 'goods',
            'quantity'            => 8,
            'quantity_size'       => '3.5" HDD, SATA, 7200 RPM',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id'      => FundSource::where('code', 'COB')->value('id'),
            'proc_start'          => '2027-02',
            'proc_end'            => '2027-04',
            'delivery_period'     => 'June 2027',
            'estimated_budget'    => '64,000.00',
            'supporting_documents' => 'Technical Specs, Market Scoping',
        ], $overrides);
    }

    /** A section PPMP with one PAP and one project, submitted. */
    protected function submittedSectionPpmp(Office $office, User $user, string $budget): Ppmp
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($office, 2027, $user);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'General Administration');
        $service->addItem($ppmp, array_merge($this->project($pap->id), ['proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => $budget]));
        $service->submit($ppmp, $user);

        return $ppmp->fresh();
    }

    public function test_section_builds_ppmp_with_paps_and_submits(): void
    {
        $response = $this->actingAs($this->staff)->postJson(route('procurement.ppmp.store'), [
            'office_id' => $this->section->id, 'fiscal_year' => 2027,
        ])->assertOk();
        $ppmp = Ppmp::where('uuid', $response->json('uuid'))->sole();
        $this->assertSame('05012-2027-V1', $ppmp->ppmp_no);

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('Add PAP')->assertSee('No PAPs yet');

        // PAP: code suggested as YY-office-NN, editable, unique per PPMP
        $this->get(route('procurement.ppmp.paps.entry', $ppmp))->assertOk()->assertSee('value="27-05012-01"', false);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-01', 'title' => 'ICT Infrastructure Management'])->assertOk();
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-01', 'title' => 'Duplicate'])->assertStatus(422);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '', 'title' => ''])->assertStatus(422)->assertJsonValidationErrors(['code', 'title']);
        $this->get(route('procurement.ppmp.paps.entry', $ppmp))->assertSee('value="27-05012-02"', false);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '25-05012-04', 'title' => 'Carried over PAP'])->assertOk();
        $pap = $ppmp->paps()->where('code', '27-05012-01')->first();
        $carried = $ppmp->paps()->where('code', '25-05012-04')->first();

        // Projects go under a PAP
        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'pap_id' => $pap->id]))->assertOk()->assertSee('27-05012-01 - ICT Infrastructure Management');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id))->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), array_diff_key($this->project($pap->id), ['ppmp_pap_id' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors('ppmp_pap_id');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, ['description' => 'Supply and Delivery of RAM', 'estimated_budget' => '30000']))->assertOk();
        $this->assertEquals('94000.00', $ppmp->fresh()->total_budget);

        // A PAP with projects cannot be removed; an empty one can
        $this->deleteJson(route('procurement.ppmp.paps.destroy', $ppmp), ['id' => $pap->id])->assertStatus(422);
        $this->deleteJson(route('procurement.ppmp.paps.destroy', $ppmp), ['id' => $carried->id])->assertOk();

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()
            ->assertSee('PAP CODE: 27-05012-01 - ICT Infrastructure Management')
            ->assertSee('QTY: 8')
            ->assertSee('Supply and Delivery of RAM');

        $this->postJson(route('procurement.ppmp.submit', $ppmp))->assertOk();
        $this->assertSame(PpmpStatus::Submitted, $ppmp->fresh()->status);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-09', 'title' => 'Late'])->assertStatus(422);
    }

    public function test_division_head_returns_then_approves_as_ppmp_no_1_and_amendment_as_no_2(): void
    {
        $mis = $this->submittedSectionPpmp($this->section, $this->staff, '64000');
        $research = app(PpmpService::class)->create($this->section2, 2027, $this->staff2);   // still a draft

        // Head returns from the section page with a reason
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $mis))->assertOk()
            ->assertSee('Approve in Division PPMP')->assertSee('id="btn_return"', false);
        $this->postJson(route('procurement.ppmp.return', $mis), ['remarks' => 'Add the brand-neutral specs'])->assertOk();
        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.submit', $mis))->assertOk();

        // Division page: section statuses, preview, approve (only the head)
        $this->actingAs($this->head)->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()
            ->assertSee('PLANNING, POLICY AND PROGRAMMING DIVISION')
            ->assertSee('05012-2027-V1')
            ->assertSee('Approve 1 submitted')
            ->assertSee('Not yet submitted (will not be included): PLANNING AND POLICY RESEARCH SECTION');
        $this->get(route('procurement.division-ppmp.preview', [$this->division, 'fy' => 2027]))->assertOk()
            ->assertSee('PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) NO. 1')->assertSee('FOR APPROVAL');

        $this->actingAs($this->staff)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertStatus(422);

        $response = $this->actingAs($this->head)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();
        $no1 = DivisionPpmp::sole();
        $this->assertSame(route('procurement.division-ppmp.show', $no1), $response->json('url'));
        $this->assertSame(1, $no1->ppmp_number);
        $this->assertEquals('64000.00', $no1->total_budget);
        $this->assertSame(PpmpStatus::Approved, $mis->fresh()->status);
        $this->assertSame(PpmpStatus::Draft, $research->fresh()->status);

        // Official print: matches the SRA form
        $this->get(route('procurement.division-ppmp.print', $no1))->assertOk()
            ->assertSee('PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) NO. 1')
            ->assertSee('Sugar Center Building, North Avenue, Diliman, Quezon City')
            ->assertSee('PAP CODE: 27-05012-01 - General Administration')
            ->assertSee('February 2027')
            ->assertSee('Corporate Operating Budget (COB)')
            ->assertSee('₱64,000.00')
            ->assertSee('TOTAL BUDGET:')
            ->assertSee('Anne Dilay')
            ->assertSee('Digna D. Gonzales')
            ->assertSee('[Head of the End-User or Implementing Unit]')
            ->assertDontSee('class="watermark"', false);

        // The research section submits, MIS amends -> next approval is PPMP No. 2 with both
        $service = app(PpmpService::class);
        $pap = $service->addPap($research, '27-05011-01', 'Preparation & Review of SRA Plans, Programs, and Policies');
        $service->addItem($research, array_merge($this->project($pap->id), ['proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => '26000']));
        $service->submit($research, $this->staff2);

        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.amend', $mis))->assertOk();
        $misV2 = Ppmp::where('amended_from_id', $mis->id)->sole();
        $this->assertSame('05012-2027-V2', $misV2->ppmp_no);
        $this->assertSame(['27-05012-01'], $misV2->paps()->pluck('code')->all());
        $service->updateItem($misV2->items()->first(), ['estimated_budget' => '80000']);
        $service->submit($misV2->fresh(), $this->staff);

        $this->actingAs($this->head)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();

        $no2 = DivisionPpmp::where('ppmp_number', 2)->sole();
        $this->assertSame(DivisionPpmp::STATUS_SUPERSEDED, $no1->fresh()->status);
        $this->assertEquals('106000.00', $no2->total_budget);
        $this->assertEqualsCanonicalizing([$misV2->id, $research->id], $no2->ppmps()->pluck('ppmps.id')->all());
        $this->assertSame(PpmpStatus::Superseded, $mis->fresh()->status);

        $this->get(route('procurement.division-ppmp.show', $no2))->assertOk()->assertSee('PPMP No. 2')->assertSee('05011-2027-V1');
        $this->get(route('procurement.division-ppmp.print', $no1))->assertOk()->assertSee('SUPERSEDED');
        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $misV2))->assertSee('PPMP No. 2');
    }

    public function test_other_offices_cannot_view_or_change(): void
    {
        $ppmp = $this->submittedSectionPpmp($this->section, $this->staff, '64000');
        $pap = $ppmp->paps()->first();

        $this->actingAs($this->outsider)->get(route('procurement.ppmp.show', $ppmp))->assertForbidden();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id))->assertForbidden();
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => 'x', 'title' => 'y'])->assertForbidden();
        $this->get(route('procurement.ppmp.print', $ppmp))->assertForbidden();

        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->division, 2027, $this->head, $this->oic);
        $this->get(route('procurement.division-ppmp.show', DivisionPpmp::sole()))->assertForbidden();
        $this->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()->assertDontSee('05012-2027-V1');

        // Head views the section PPMP read-only
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertDontSee('Add PAP');
    }

    public function test_delete_draft_needs_permission_and_frees_the_number(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $ppmp))->assertDontSee('id="btn_delete_ppmp"', false);
        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertForbidden();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'menu.ppmp-destroy', 'guard_name' => 'web']);
        $this->staff->givePermissionTo('menu.ppmp-destroy');

        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertOk();
        $this->assertSame(0, Ppmp::withTrashed()->count());
        $this->assertSame('05012-2027-V1', $service->create($this->section, 2027, $this->staff)->ppmp_no);
    }

    public function test_section_copy_print(): void
    {
        $ppmp = $this->submittedSectionPpmp($this->section, $this->staff, '64000');

        $this->actingAs($this->staff)->get(route('procurement.ppmp.print', $ppmp))->assertOk()
            ->assertSee('PAP CODE: 27-05012-01 - General Administration')
            ->assertSee('Specs: 3.5&quot; HDD, SATA, 7200 RPM', false)
            ->assertSee('class="watermark">DRAFT', false)
            ->assertDontSee('(PPMP) NO.');
    }

    public function test_unit_price_times_quantity_is_the_estimated_budget(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);
        $pap = $service->addPap($ppmp, '27-05012-01', 'ICT Infrastructure Management');

        // Typed budget is ignored when quantity and unit price are given
        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['quantity' => 8, 'unit_cost' => '8,000.00', 'estimated_budget' => '1']))->assertOk();
        $item = $ppmp->items()->sole();
        $this->assertEquals('8000.00', $item->unit_cost);
        $this->assertEquals('64000.00', $item->estimated_budget);

        // Changing the quantity recomputes it (centavos stay exact)
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['id' => $item->id, 'quantity' => 3, 'unit_cost' => '333.33']))->assertOk();
        $this->assertEquals('999.99', $item->fresh()->estimated_budget);

        // A lot without unit price keeps the typed budget; budget is required then
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['description' => 'Consultancy', 'quantity' => null, 'unit_cost' => '', 'estimated_budget' => '2,500,000']))->assertOk();
        $this->assertEquals('2500000.00', $ppmp->items()->where('description', 'Consultancy')->value('estimated_budget'));
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['quantity' => 2, 'unit_cost' => '', 'estimated_budget' => '']))->assertStatus(422)->assertJsonValidationErrors('estimated_budget');

        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'id' => $item->id]))->assertOk()->assertSee('value="333.33"', false);
        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('@ ₱333.33');

        // Amendments keep the unit price
        $service->submit($ppmp->fresh(), $this->staff);
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->division, 2027, $this->head, $this->oic);
        $copy = $service->amend($ppmp->fresh(), $this->staff);
        $this->assertEquals('333.33', $copy->items()->where('description', '!=', 'Consultancy')->value('unit_cost'));
    }
}
