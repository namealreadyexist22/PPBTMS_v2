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

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);
        $this->ppmps = app(PpmpService::class);
        $this->budget = app(BudgetAllocationService::class);
        $this->officer = User::factory()->create();

        $head = User::factory()->create();
        $this->ppspd = Office::create(['code' => '05000', 'name' => 'PPSPD', 'head_user_id' => $head->id, 'is_consolidating' => true, 'is_department' => true]);
        $this->pppd = Office::create(['code' => '05010', 'name' => 'PPPD', 'parent_id' => $this->ppspd->id]);
        $this->research = Office::create(['code' => '05011', 'name' => 'PPRS', 'parent_id' => $this->pppd->id]);
        $this->mis = Office::create(['code' => '05012', 'name' => 'MIS', 'parent_id' => $this->pppd->id]);
        $this->sppdemd = Office::create(['code' => '05020', 'name' => 'SPPDEMD', 'parent_id' => $this->ppspd->id]);
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
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '5000000', $this->officer);   // PPSPD 5M (CO + MOOE)

        $this->ppmp($this->research, [['co', '2500000', 'COB'], ['mooe', '500000', 'COB']]);   // first: 3M fits
        $late = $this->ppmp($this->mis, [['co', '1500000', 'COB'], ['mooe', '1000000', 'COB']], false);   // 3M + 2.5M > 5M

        // One row: the department's budget; others = PPRS's 3M, left to plan = 2M - 2.5M
        $rows = $this->budget->checkPpmp($late);
        $this->assertCount(1, $rows);
        $this->assertSame($this->ppspd->id, $rows->first()['department']->id);
        $this->assertSame(300000000, $rows->first()['others']);
        $this->assertTrue($rows->first()['over']);
        $limit = $this->budget->limits($late)['regular'];
        $this->assertSame(-50000000, $limit['available'] - $limit['mine']);

        $this->refused(fn () => $this->ppmps->submit($late, User::factory()->create()), 'COB budget of 05000: ₱5,500,000.00 used of ₱5,000,000.00 (over by ₱500,000.00)');
        $this->assertSame(PpmpStatus::Draft, $late->fresh()->status);

        // Brought within what is left -> submits
        $this->ppmps->updateItem($late->items()->where('allotment_class', 'co')->first(), ['estimated_budget' => '1000000']);
        $this->ppmps->submit($late->fresh(), User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $late->fresh()->status);
    }

    public function test_only_departments_get_budgets_and_a_department_below_has_its_own(): void
    {
        $this->refused(fn () => $this->budget->save($this->mis, 2027, FundGroup::Regular, '1000000', $this->officer), 'allocated per department');

        // SPPDEMD made a department of its own: its PPMPs use its budget, not PPSPD's
        $this->sppdemd->update(['is_department' => true]);
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '1000000', $this->officer);
        $this->budget->save($this->sppdemd, 2027, FundGroup::Regular, '3000000', $this->officer);

        $this->assertEqualsCanonicalizing([$this->ppspd->id, $this->pppd->id, $this->research->id, $this->mis->id], $this->ppspd->departmentOfficeIds());
        $this->ppmp($this->sppdemd, [['co', '2500000', 'COB']]);                // within SPPDEMD's 3M
        $this->assertSame(0, $this->budget->usedCents($this->budget->find($this->ppspd, 2027, FundGroup::Regular)->setRelation('office', $this->ppspd)));

        // No allocation for SIDA: shown, not blocked; drafts hold nothing
        $sida = $this->ppmp($this->mis, [['co', '900000', 'SIDA']], false);
        $this->assertNull($this->budget->checkPpmp($sida)->firstWhere('fund', FundGroup::Sida)['allocation']);
        $this->assertArrayNotHasKey('sida', $this->budget->limits($sida));
        $this->ppmps->submit($sida, User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $sida->fresh()->status);
    }

    public function test_amendment_replaces_its_earlier_version_when_counting(): void
    {
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '1000000', $this->officer);
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
        $this->sppdemd->update(['is_department' => true]);
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '5000000', $this->officer);
        $this->budget->save($this->sppdemd, 2027, FundGroup::Regular, '3000000', $this->officer);

        // A change needs a reason
        $this->refused(fn () => $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '4000000', $this->officer), 'reason');

        // Cannot go below what submitted PPMPs use: amend the PPMP first
        $this->ppmp($this->mis, [['co', '4000000', 'COB'], ['mooe', '500000', 'COB']]);
        $this->refused(fn () => $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '4000000', $this->officer, 'realign'), 'already use');

        // Realignment: SPPDEMD gives 500k to PPSPD
        $this->budget->save($this->sppdemd, 2027, FundGroup::Regular, '2500000', $this->officer, 'Realign 500k to PPSPD');
        $ppspd = $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '5500000', $this->officer, 'Realign 500k from SPPDEMD');

        $this->assertEquals('5500000.00', $ppspd->amount);
        $this->assertSame(['Realign 500k from SPPDEMD', null], $ppspd->history()->pluck('reason')->all());
        $this->assertEquals('5000000.00', $ppspd->history()->first()->old_amount);
    }

    public function test_budget_page_and_ppmp_warning(): void
    {
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->officer->update(['is_activated' => 1]);
        $this->officer->givePermissionTo('manage budget');

        $this->actingAs($this->officer)->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()
            ->assertSee('05000')->assertSee('Not set')->assertDontSee('05012 MIS</span>', false);
        $this->postJson(route('procurement.budget.store'), ['office_id' => $this->ppspd->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '3,000,000.00'])->assertOk();
        $this->postJson(route('procurement.budget.store'), ['office_id' => $this->mis->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '1,000,000'])
            ->assertStatus(422)->assertJson(['status' => 'error']);   // not a department

        $this->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()
            ->assertSee('3,000,000.00')->assertSee('5 offices')->assertSee('Initial allocation');

        // PPMP page: department budget at the top, warning and disabled submit while over budget
        $staffPpmp = $this->ppmp($this->mis, [['co', '3500000', 'COB']], false);
        $staff = User::find($staffPpmp->created_by);
        $staff->update(['is_activated' => 1]);
        $staff->givePermissionTo('manage ppmp');
        $this->actingAs($staff)->get(route('procurement.ppmp.show', $staffPpmp))->assertOk()
            ->assertSee('Over budget.')->assertSee('Budget Allocation')->assertSee('budget, shared by its offices')
            ->assertSee('id="btn_submit" disabled', false)
            ->assertSee('over by ₱500,000.00')
            ->assertSee('-500,000.00');
        $this->postJson(route('procurement.ppmp.submit', $staffPpmp))->assertStatus(422);

        // Add-project modal: what is left for this PPMP before the new project (3M - 3.5M = -500k)
        $this->get(route('procurement.ppmp.items.entry', $staffPpmp))->assertOk()
            ->assertSee('"regular":{"left":-50000000', false)->assertSee('id="budget_left"', false);
    }
}
