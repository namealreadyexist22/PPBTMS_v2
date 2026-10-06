<?php

namespace Tests\Feature\Procurement;

use App\Enums\FundGroup;
use App\Enums\PpmpStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\BudgetAllocationService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BudgetAllocationTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected BudgetAllocationService $budget;
    protected User $officer;
    protected Office $ppspd;
    protected Office $pppd;
    protected Office $research;
    protected Office $mis;
    protected Office $sppdemd;
    protected Office $dept;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);
        $this->ppmps = app(PpmpService::class);
        $this->budget = app(BudgetAllocationService::class);
        $this->officer = User::factory()->create();

        $head = User::factory()->create();
        // PPSPD the department (it also prepares its own PPMP), PPPD and SPPDEM its divisions, PPRS and MIS PPPD's sections
        $this->ppspd = Office::create(['code' => '05000', 'acronym' => 'PPSPD', 'name' => 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular', 'head_user_id' => $head->id, 'is_consolidating' => true]);
        $this->dept = $this->ppspd;
        $this->pppd = Office::create(['code' => '05010', 'acronym' => 'PPPD', 'name' => 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'type' => 'division', 'parent_id' => $this->ppspd->id]);
        $this->research = Office::create(['code' => '05011', 'acronym' => 'PPRS', 'name' => 'PLANNING AND POLICY RESEARCH SECTION', 'type' => 'section', 'parent_id' => $this->pppd->id]);
        $this->mis = Office::create(['code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS SECTION', 'type' => 'section', 'parent_id' => $this->pppd->id]);
        $this->sppdemd = Office::create(['code' => '05020', 'acronym' => 'SPPDEM', 'name' => 'SPECIAL PROJECTS DIVISION', 'type' => 'division', 'parent_id' => $this->ppspd->id]);
    }

    /** A PPMP with projects [[class, budget, fund code]], optionally submitted. */
    protected function ppmp(Office $office, array $projects, bool $submit = true): Ppmp
    {
        $staff = User::factory()->create(['office_id' => $office->id]);
        $ppmp = $this->ppmps->create($office, 2027, $staff);
        $pap = $this->ppmps->addPap($ppmp, $this->ppmps->suggestPapCode($ppmp), 'General');

        foreach ($projects as [$class, $budget, $fund]) {
            $this->ppmps->addItem($ppmp, [
                'ppmp_pap_id' => $pap->id, 'description' => "{$class} item", 'project_type' => 'goods', 'allotment_class' => $class,
                'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
                'fund_source_id' => FundSource::where('code', $fund ?? 'COB')->value('id'),
                'proc_start' => '2027-01-01', 'proc_end' => '2027-02-01', 'estimated_budget' => $budget,
            ]);
        }

        if ($submit) {
            $this->ppmps->submit($ppmp, $staff);
        }

        return $ppmp->fresh();
    }

    protected function refused(callable $action, string $contains = ''): void
    {
        try {
            $action();
            $this->fail('Expected a ProcurementException.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function test_department_budget_is_shared_first_come_and_blocks_submit(): void
    {
        $this->budget->save($this->dept, 2027, FundGroup::Regular, '5000000', $this->officer);   // PPSPD 5M (CO + MOOE)

        $this->ppmp($this->ppspd, [['mooe', '1000000', 'COB']]);                                  // Manager III's own PPMP
        $this->ppmp($this->research, [['co', '1500000', 'COB'], ['mooe', '500000', 'COB']]);     // 1M + 2M = 3M fits
        $late = $this->ppmp($this->mis, [['co', '1500000', 'COB'], ['mooe', '1000000', 'COB']], false);   // 3M + 2.5M > 5M

        // One row: the department's budget; others = 3M, left to plan = 2M - 2.5M
        $rows = $this->budget->checkPpmp($late);
        $this->assertCount(1, $rows);
        $this->assertSame($this->dept->id, $rows->first()['department']->id);
        $this->assertSame(300000000, $rows->first()['others']);
        $this->assertTrue($rows->first()['over']);
        $limit = $this->budget->limits($late)['regular'];
        $this->assertSame(-50000000, $limit['available'] - $limit['mine']);

        $this->refused(fn () => $this->ppmps->submit($late, User::factory()->create()), 'COB budget of PPSPD: ₱5,500,000.00 used of ₱5,000,000.00 (over by ₱500,000.00)');
        $this->assertSame(PpmpStatus::Draft, $late->fresh()->status);

        // Brought within what is left -> submits
        $this->ppmps->updateItem($late->items()->where('allotment_class', 'co')->first(), ['estimated_budget' => '1000000']);
        $this->ppmps->submit($late->fresh(), User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $late->fresh()->status);
    }

    public function test_offices_of_another_department_or_none_do_not_share(): void
    {
        $other = Office::create(['code' => '04000', 'acronym' => 'LEGAL', 'name' => 'LEGAL DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular']);
        $legal = $other;
        $loose = Office::create(['code' => '99000', 'name' => 'NO DEPARTMENT', 'type' => 'section']);
        // A department further down keeps its own budget (e.g. AFD-LM under ODA-AF)
        $sub = Office::create(['code' => '05030', 'acronym' => 'SUB', 'name' => 'SUB DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular', 'parent_id' => $this->ppspd->id]);
        Office::create(['code' => '05031', 'name' => 'SUB SECTION', 'type' => 'section', 'parent_id' => $sub->id]);
        $this->assertEqualsCanonicalizing([$this->ppspd->id, $this->pppd->id, $this->research->id, $this->mis->id, $this->sppdemd->id], $this->ppspd->departmentOfficeIds());

        $this->budget->save($this->dept, 2027, FundGroup::Regular, '1000000', $this->officer);
        $this->budget->save($other, 2027, FundGroup::Regular, '3000000', $this->officer);

        $this->ppmp($legal, [['co', '2500000', 'COB']]);   // within LEGAL's 3M; PPSPD's 1M untouched
        $this->assertSame(0, $this->budget->usedCents($this->budget->find($this->dept, 2027, FundGroup::Regular)->setRelation('department', $this->dept)));

        // An office with no department has no budget: shown, not checked
        $unchecked = $this->ppmp($loose, [['co', '900000', 'COB']], false);
        $this->assertNull($this->budget->checkPpmp($unchecked)->first()['allocation']);
        $this->ppmps->submit($unchecked, User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $unchecked->fresh()->status);
    }

    public function test_sida_departments_get_sida_budgets_and_others_cob(): void
    {
        $sidaDept = Office::create(['code' => '11000', 'acronym' => 'SIDA-SCP', 'name' => 'SIDA-SCP', 'type' => 'department', 'budget_fund' => 'sida']);
        $sida = $sidaDept;

        // Each department only takes a budget in its own fund
        $this->refused(fn () => $this->budget->save($this->dept, 2027, FundGroup::Sida, '1000000', $this->officer), 'budgeted under COB, not SIDA');
        $this->refused(fn () => $this->budget->save($sidaDept, 2027, FundGroup::Regular, '1000000', $this->officer), 'budgeted under SIDA, not COB');
        $this->budget->save($sidaDept, 2027, FundGroup::Sida, '50000000', $this->officer);
        $this->budget->save($this->dept, 2027, FundGroup::Regular, '5000000', $this->officer);

        // A COB department's PPMP with a SIDA project cannot be submitted
        $mixed = $this->ppmp($this->mis, [['co', '100000', 'COB'], ['mooe', '50000', 'SIDA']], false);
        $this->assertTrue($this->budget->checkPpmp($mixed)->firstWhere('fund', FundGroup::Sida)['wrong_fund']);
        $this->refused(fn () => $this->ppmps->submit($mixed, User::factory()->create()), 'PPSPD has no SIDA budget');

        // The SIDA department's PPMP is checked against its SIDA budget
        $sidaPpmp = $this->ppmp($sida, [['co', '20000000', 'SIDA']]);
        $this->assertSame(PpmpStatus::Submitted, $sidaPpmp->status);
        $limit = $this->budget->limits($sidaPpmp)['sida'];
        $this->assertSame(3000000000, $limit['available'] - $limit['mine']);   // 30M left of 50M
    }

    public function test_amendment_replaces_its_earlier_version_when_counting(): void
    {
        $this->budget->save($this->dept, 2027, FundGroup::Regular, '1000000', $this->officer);
        $v1 = $this->ppmp($this->mis, [['co', '800000', 'COB']]);

        // Approve, then amend to 950k: counted instead of v1 (not 800k + 950k)
        $head = $this->ppspd->head;
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->ppspd, 2027, \App\Enums\Region::Lm, $head, $head);
        $staff = User::factory()->create(['office_id' => $this->mis->id]);
        $v2 = $this->ppmps->amend($v1->fresh(), $staff);
        $this->ppmps->updateItem($v2->items()->first(), ['estimated_budget' => '950000']);
        $this->ppmps->submit($v2->fresh(), $staff);

        $this->assertSame(PpmpStatus::Submitted, $v2->fresh()->status);
    }

    public function test_realignment_between_departments_with_reason_and_history(): void
    {
        $other = Office::create(['code' => '07020', 'acronym' => 'RDE-LM', 'name' => 'RDE-LM', 'type' => 'department', 'budget_fund' => 'regular']);
        $this->budget->save($this->dept, 2027, FundGroup::Regular, '5000000', $this->officer);
        $this->budget->save($other, 2027, FundGroup::Regular, '3000000', $this->officer);

        // A change needs a reason
        $this->refused(fn () => $this->budget->save($this->dept, 2027, FundGroup::Regular, '4000000', $this->officer), 'reason');

        // Cannot go below what submitted PPMPs use: amend the PPMP first
        $this->ppmp($this->mis, [['co', '4000000', 'COB'], ['mooe', '500000', 'COB']]);
        $this->refused(fn () => $this->budget->save($this->dept, 2027, FundGroup::Regular, '4000000', $this->officer, 'realign'), 'already use');

        // Realignment: RDE-LM gives 500k to PPSPD
        $this->budget->save($other, 2027, FundGroup::Regular, '2500000', $this->officer, 'Realign 500k to PPSPD');
        $ppspd = $this->budget->save($this->dept, 2027, FundGroup::Regular, '5500000', $this->officer, 'Realign 500k from RDE-LM');

        $this->assertEquals('5500000.00', $ppspd->amount);
        $this->assertSame(['Realign 500k from RDE-LM', null], $ppspd->history()->pluck('reason')->all());
        $this->assertEquals('5000000.00', $ppspd->history()->first()->old_amount);
    }

    public function test_budget_page_and_ppmp_warning(): void
    {
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->officer->update(['is_activated' => 1]);
        $this->officer->givePermissionTo('manage budget');
        Office::create(['code' => '12000', 'acronym' => 'SIDA-HRD', 'name' => 'SIDA-HRD', 'type' => 'department', 'budget_fund' => 'sida']);

        $this->actingAs($this->officer)->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()
            ->assertSee('PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT')->assertSee('5 offices')->assertSee('Not set')->assertDontSee('SIDA-HRD');
        $this->get(route('procurement.budget.index', ['fy' => 2027, 'fund' => 'sida']))->assertOk()
            ->assertSee('SIDA-HRD')->assertDontSee('PLANNING, POLICY');

        $this->postJson(route('procurement.budget.store'), ['department_id' => $this->dept->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '3,000,000.00'])->assertOk();
        $this->postJson(route('procurement.budget.store'), ['department_id' => $this->dept->id, 'fiscal_year' => 2027, 'fund_group' => 'sida', 'amount' => '1,000,000'])
            ->assertStatus(422)->assertJson(['status' => 'error']);   // PPSPD is COB
        $this->postJson(route('procurement.budget.store'), ['department_id' => $this->pppd->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '1,000,000'])
            ->assertStatus(422)->assertJson(['message' => 'Budgets are allocated per department. PPPD is a Division.']);

        $this->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()
            ->assertSee('3,000,000.00')->assertSee('05012 MIS SECTION')->assertSee('Initial allocation');

        // PPMP page: department budget at the top, warning and disabled submit while over budget
        $staffPpmp = $this->ppmp($this->mis, [['co', '3500000', 'COB']], false);
        $staff = User::find($staffPpmp->created_by);
        $staff->update(['is_activated' => 1]);
        $staff->givePermissionTo('manage ppmp');
        $this->actingAs($staff)->get(route('procurement.ppmp.show', $staffPpmp))->assertOk()
            ->assertSee('Over budget.')->assertSee('PPSPD — PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT budget, shared by its units')
            ->assertSee('id="btn_submit" disabled', false)
            ->assertSee('over by ₱500,000.00')
            ->assertSee('-500,000.00');
        $this->postJson(route('procurement.ppmp.submit', $staffPpmp))->assertStatus(422);

        // Add-project modal: what is left for this PPMP before the new project (3M - 3.5M = -500k)
        $this->get(route('procurement.ppmp.items.entry', $staffPpmp))->assertOk()
            ->assertSee('"regular":{"left":-50000000', false)->assertSee('id="budget_left"', false);
    }
}
