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
        $this->ppspd = Office::create(['code' => '05000', 'name' => 'PPSPD', 'head_user_id' => $head->id, 'is_consolidating' => true]);
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

    public function test_section_cap_blocks_submit_and_shows_the_remaining_budget(): void
    {
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '15000000', $this->officer);   // PPSPD 15M (CO + MOOE)
        $this->budget->save($this->mis, 2027, FundGroup::Regular, '4000000', $this->officer);      // MIS 4M

        $draft = $this->ppmp($this->mis, [['co', '3500000', 'COB'], ['mooe', '1000000', 'COB']], false);   // 4.5M

        // CO and MOOE count together against MIS's 4M; PPSPD's row is shown too
        $rows = $this->budget->checkPpmp($draft);
        $misRow = $rows->first(fn ($r) => $r['allocation']->office_id === $this->mis->id);
        $this->assertTrue($misRow['over']);
        $this->assertSame(-50000000, $misRow['remaining']);   // ₱500,000 over, in centavos
        $this->assertCount(2, $rows);

        // Left to plan = tightest allocation (MIS) minus this PPMP
        $limit = $this->budget->limits($draft)['regular'];
        $this->assertSame(400000000 - 450000000, $limit['available'] - $limit['mine']);
        $this->assertSame($this->mis->id, $limit['office']->id);

        $this->refused(fn () => $this->ppmps->submit($draft, User::factory()->create()), 'COB budget of 05012');
        $this->assertSame(PpmpStatus::Draft, $draft->fresh()->status);

        // Brought within the cap -> submits
        $this->ppmps->updateItem($draft->items()->where('allotment_class', 'co')->first(), ['estimated_budget' => '3000000']);
        $this->ppmps->submit($draft->fresh(), User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $draft->fresh()->status);
    }

    public function test_sections_without_own_cap_share_the_department_pool_first_come(): void
    {
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '1000000', $this->officer);   // 1M for all of PPSPD

        $this->ppmp($this->research, [['co', '500000', 'COB'], ['mooe', '200000', 'COB']]);   // first: 700k fits
        $late = $this->ppmp($this->sppdemd, [['mooe', '400000', 'COB']], false);               // 700k + 400k > 1M

        $limit = $this->budget->limits($late)['regular'];
        $this->assertSame(30000000, $limit['available']);   // 300k left for SPPDEMD
        $this->refused(fn () => $this->ppmps->submit($late, User::factory()->create()), 'over by ₱100,000.00');

        // Drafts do not hold budget; SIDA has its own allocation (none set = no cap, only shown)
        $sida = $this->ppmp($this->mis, [['co', '900000', 'SIDA']], false);
        $this->assertNull($this->budget->checkPpmp($sida)->firstWhere('fund', FundGroup::Sida)['allocation']);
        $this->assertArrayNotHasKey('sida', $this->budget->limits($sida));
        $this->ppmps->submit($sida, User::factory()->create());
        $this->assertSame(PpmpStatus::Submitted, $sida->fresh()->status);
    }

    public function test_amendment_replaces_its_earlier_version_when_counting(): void
    {
        $this->budget->save($this->mis, 2027, FundGroup::Regular, '1000000', $this->officer);
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

    public function test_realignment_guards_and_history(): void
    {
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '10000000', $this->officer);
        $this->budget->save($this->mis, 2027, FundGroup::Regular, '3000000', $this->officer);
        $this->budget->save($this->sppdemd, 2027, FundGroup::Regular, '6000000', $this->officer);

        // Sections cannot together get more than the department
        $this->refused(fn () => $this->budget->save($this->research, 2027, FundGroup::Regular, '2000000', $this->officer), 'more than what is left of 05000');
        // ...and the department cannot drop below what it handed out
        $this->refused(fn () => $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '8000000', $this->officer, 'cut'), 'offices under 05000');
        // A change needs a reason
        $this->refused(fn () => $this->budget->save($this->mis, 2027, FundGroup::Regular, '2000000', $this->officer), 'reason');

        // Cannot go below what submitted PPMPs use: amend the PPMP first
        $this->ppmp($this->mis, [['co', '2000000', 'COB'], ['mooe', '500000', 'COB']]);
        $this->refused(fn () => $this->budget->save($this->mis, 2027, FundGroup::Regular, '2000000', $this->officer, 'realign'), 'already use');

        // Realignment between divisions: SPPDEMD gives 500k to MIS (department total unchanged)
        $this->budget->save($this->sppdemd, 2027, FundGroup::Regular, '5500000', $this->officer, 'Realign 500k to MIS');
        $mis = $this->budget->save($this->mis, 2027, FundGroup::Regular, '3500000', $this->officer, 'Realign 500k from SPPDEMD');

        $this->assertEquals('3500000.00', $mis->amount);
        $this->assertSame(['Realign 500k from SPPDEMD', null], $mis->history()->pluck('reason')->all());
        $this->assertEquals('3000000.00', $mis->history()->first()->old_amount);
    }

    public function test_budget_page_and_ppmp_warning(): void
    {
        $this->withoutVite();
        $this->seed(\Database\Seeders\RolePermissionSeeder::class);
        $this->officer->update(['is_activated' => 1]);
        $this->officer->givePermissionTo('manage budget');

        $this->actingAs($this->officer)->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()->assertSee('No COB budget set for FY 2027');
        $this->postJson(route('procurement.budget.store'), ['office_id' => $this->ppspd->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '15,000,000.00'])->assertOk();
        $this->postJson(route('procurement.budget.store'), ['office_id' => $this->mis->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '3,000,000'])->assertOk();
        $this->postJson(route('procurement.budget.store'), ['office_id' => $this->research->id, 'fiscal_year' => 2027, 'fund_group' => 'regular', 'amount' => '13,000,000'])
            ->assertStatus(422)->assertJson(['status' => 'error']);   // more than left of PPSPD's

        $this->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()
            ->assertSee('15,000,000.00')->assertSee('↳ 3,000,000.00')->assertSee('Initial allocation');

        // PPMP page: budget left at the top, warning and disabled submit while over budget
        $staffPpmp = $this->ppmp($this->mis, [['co', '3500000', 'COB']], false);
        $staff = User::find($staffPpmp->created_by);
        $staff->update(['is_activated' => 1]);
        $staff->givePermissionTo('manage ppmp');
        $this->actingAs($staff)->get(route('procurement.ppmp.show', $staffPpmp))->assertOk()
            ->assertSee('Over budget.')->assertSee('Budget Allocation')
            ->assertSee('id="btn_submit" disabled', false)
            ->assertSee('over by ₱500,000.00')
            ->assertSee('05012 MIS')->assertDontSee('05000 PPSPD')   // only the section's own budget
            ->assertSee('-500,000.00');
        $this->postJson(route('procurement.ppmp.submit', $staffPpmp))->assertStatus(422);

        // A division without its own budget sees the shared one above it (PPSPD's 15M; MIS's draft holds nothing)
        $shared = $this->ppmp($this->sppdemd, [['mooe', '100000', 'COB']], false);
        $sharedStaff = User::find($shared->created_by);
        $sharedStaff->update(['is_activated' => 1]);
        $sharedStaff->givePermissionTo('manage ppmp');
        $this->actingAs($sharedStaff)->get(route('procurement.ppmp.show', $shared))->assertOk()
            ->assertSee('05000 PPSPD')->assertSee('Used by other offices')->assertSee('₱14,900,000.00 left to plan');
        $this->actingAs($staff);

        // Add-project modal: what is left for this PPMP before the new project (3M - 3.5M = -500k)
        $this->get(route('procurement.ppmp.items.entry', $staffPpmp))->assertOk()
            ->assertSee('"regular":{"left":-50000000', false)->assertSee('id="budget_left"', false);
    }
}
