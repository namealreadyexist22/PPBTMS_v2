<?php

namespace Tests\Feature\Procurement;

use App\Enums\FundGroup;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\ExpenseClass;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Item;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\Location;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpPap;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\BudgetAllocationService;
use App\Services\Procurement\CobReportService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Corporate Operating Budget: budget-only lines, expense classes and locations on the PPMP, and the
 * printouts (Breakdown per Office / Department, Budget Proposal, Details of MOOE and of CO).
 */
class CobReportTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected BudgetAllocationService $budget;
    protected CobReportService $reports;
    protected Office $ppspd;
    protected Office $otm;
    protected Office $pppd;
    protected Office $research;
    protected Office $mis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);
        $this->ppmps = app(PpmpService::class);
        $this->budget = app(BudgetAllocationService::class);
        $this->reports = app(CobReportService::class);

        $head = User::factory()->create();
        // PPSPD: Office of the Manager and PPPD (with its sections PPRS and MIS)
        $this->ppspd = Office::create(['acronym' => 'PPSPD', 'name' => 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular', 'head_user_id' => $head->id, 'is_consolidating' => true]);
        $this->otm = Office::create(['code' => '05000', 'acronym' => 'OTM', 'name' => 'OFFICE OF THE MANAGER', 'type' => 'division', 'parent_id' => $this->ppspd->id]);
        $this->pppd = Office::create(['code' => '05010', 'acronym' => 'PPPD', 'name' => 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'type' => 'division', 'parent_id' => $this->ppspd->id]);
        $this->research = Office::create(['code' => '05011', 'acronym' => 'PPRS', 'name' => 'PLANNING AND POLICY RESEARCH SECTION', 'type' => 'section', 'parent_id' => $this->pppd->id]);
        $this->mis = Office::create(['code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS SECTION', 'type' => 'section', 'parent_id' => $this->pppd->id]);
    }

    protected function cls(string $code): int
    {
        return (int) ExpenseClass::where('code', $code)->value('id');
    }

    protected function location(string $code): int
    {
        return (int) Location::where('code', $code)->value('id');
    }

    /** A draft PPMP of the office with one PAP; returns [ppmp, pap, staff]. */
    protected function draft(Office $office, string $title = 'General'): array
    {
        $staff = User::factory()->create(['office_id' => $office->id]);
        $ppmp = $this->ppmps->create($office, 2027, $staff);
        $pap = $this->ppmps->addPap($ppmp, $this->ppmps->suggestPapCode($ppmp), $title);

        return [$ppmp, $pap, $staff];
    }

    protected function project(Ppmp $ppmp, PpmpPap $pap, array $values): \App\Models\Procurement\PpmpItem
    {
        return $this->ppmps->addItem($ppmp, $values + [
            'ppmp_pap_id' => $pap->id, 'description' => 'Project', 'project_type' => 'goods', 'allotment_class' => 'mooe',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'proc_start' => '2027-01-01', 'proc_end' => '2027-02-01',
        ]);
    }

    /** A budget line on the PPMP PAP's Budget Proposal PAP */
    protected function line(Ppmp $ppmp, PpmpPap $pap, string $class, string $amount, ?string $location = null): \App\Models\Procurement\BudgetLine
    {
        return $this->proposals()->saveBudgetLine($pap->budgetPap, [
            'expense_class_id' => $this->cls($class), 'location_id' => $location ? $this->location($location) : null,
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'), 'description' => "{$class} line", 'amount' => $amount,
        ]);
    }

    protected function proposals(): \App\Services\Procurement\BudgetProposalService
    {
        return app(\App\Services\Procurement\BudgetProposalService::class);
    }

    public function test_budget_lines_count_once_the_proposal_is_submitted_and_the_evaluator_codes_it(): void
    {
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '100000', User::factory()->create());
        [$ppmp, $pap, $staff] = $this->draft($this->research);
        $this->project($ppmp, $pap, ['estimated_budget' => '60000']);
        $this->line($ppmp, $pap, 'TRV', '50000');
        $proposal = $pap->budgetPap->proposal;
        $this->assertSame($this->ppspd->id, $proposal->department_id);
        $pap->budgetPap->update(['code' => null]);   // no code until the evaluator gives it
        $pap->refresh();

        // A draft proposal's lines hold no budget: the PPMP (60,000) submits
        $this->assertSame(6000000, $this->budget->checkPpmp($ppmp->fresh())->first()['mine']);
        $this->ppmps->submit($ppmp->fresh(), $staff);

        // Submitting the proposal: 60,000 procured + 50,000 travel > 100,000
        try {
            $this->proposals()->submit($proposal, $staff);
            $this->fail('Expected the budget check to refuse.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString('over by ₱10,000.00', $e->getMessage());
        }
        $this->assertSame(\App\Enums\BudgetProposalStatus::Draft, $proposal->fresh()->status);

        $pap->budgetPap->budgetLines()->sole()->update(['amount' => '40000']);
        $this->proposals()->submit($proposal->fresh(), $staff);
        $this->assertSame(10000000, $this->budget->usedCents(\App\Models\Procurement\BudgetAllocation::sole()));

        // Locked once submitted; a PAP used by a PPMP cannot be removed
        $this->refused(fn () => $this->line($ppmp, $pap, 'TRN', '1000'), 'submitted');
        $this->refused(fn () => $this->proposals()->removePap($pap->budgetPap->fresh()), 'submitted');

        // The evaluator: every PAP needs its code; codes are unique; then evaluated (locked), or returned to reopen it
        $evaluator = User::factory()->create();
        $this->refused(fn () => $this->proposals()->evaluate($proposal->fresh(), $evaluator), 'without one');
        $this->assertSame([$pap->budget_pap_id => '27-05011-01'], $this->proposals()->suggestCodes($proposal->fresh()));
        $this->proposals()->setCodes($proposal->fresh(), [$pap->budget_pap_id => '27-05011-01'], $evaluator);
        $this->proposals()->evaluate($proposal->fresh(), $evaluator);
        $this->assertSame('27-05011-01', $pap->fresh()->code);
        $this->proposals()->returnToDepartment($proposal->fresh(), $evaluator, 'Add the training line');
        $this->line($ppmp, $pap->fresh(), 'TRN', '1000');
        $this->assertSame(\App\Enums\BudgetProposalStatus::Returned, $proposal->fresh()->status);

        // An amendment keeps the same Budget Proposal PAP (PR charges stay with it)
        $approved = $this->ppmps->approve($ppmp->fresh(), User::find($this->ppspd->head_user_id));
        $amendment = $this->ppmps->amend($approved, $staff);
        $this->assertSame($pap->budget_pap_id, $amendment->paps()->sole()->budget_pap_id);
        $this->assertSame('27-05011-01', $amendment->paps()->sole()->code);
    }

    protected function refused(callable $action, string $contains): void
    {
        try {
            $action();
            $this->fail('Expected a ProcurementException.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString($contains, $e->getMessage());
        }
    }

    public function test_a_project_takes_its_expense_class_from_its_class_or_category(): void
    {
        [$ppmp, $pap] = $this->draft($this->research);

        // Semi-expendable: Semi-Expendable Expenses
        $semi = $this->project($ppmp, $pap, ['allotment_class' => 'semi', 'estimated_budget' => '30000']);
        $this->assertSame($this->cls('SEMI'), $semi->expense_class_id);

        // MOOE on a standard item: its category's (Office Supplies -> Office Supplies Expenses)
        $paper = Item::create(['code' => 'OFS-001', 'name' => 'Bond paper', 'unit_id' => Unit::where('code', 'ream')->value('id'),
            'item_category_id' => ItemCategory::where('code', 'OFS')->value('id'), 'project_type' => 'goods', 'standard_unit_cost' => '250', 'is_active' => true]);
        $mooe = $this->project($ppmp, $pap, ['item_id' => $paper->id, 'quantity' => 10]);
        $this->assertSame($this->cls('OFS'), $mooe->expense_class_id);

        // Picked by the office: kept; CO: none
        $picked = $this->project($ppmp, $pap, ['expense_class_id' => $this->cls('GSV'), 'estimated_budget' => '5000']);
        $this->assertSame($this->cls('GSV'), $picked->expense_class_id);
        $co = $this->project($ppmp, $pap, ['allotment_class' => 'co', 'expense_class_id' => $this->cls('GSV'), 'estimated_budget' => '90000', 'deployment' => 'Bacolod', 'justification' => 'Replacement']);
        $this->assertNull($co->expense_class_id);
        $this->assertSame('Replacement', $co->justification);
    }

    public function test_proposal_groups_paps_and_splits_co_mooe_by_class_and_location(): void
    {
        [$own, $ownPap] = $this->draft($this->otm, 'Management and Supervision');
        $this->line($own, $ownPap, 'TRV', '75000');
        $this->line($own, $ownPap, 'REP', '150000');

        [$mis, $infra] = $this->draft($this->mis, 'ICT Infrastructure Management');
        $this->project($mis, $infra, ['allotment_class' => 'co', 'description' => 'Desktop', 'quantity' => 34, 'unit_cost' => '90000', 'location_id' => $this->location('QC'), 'deployment' => 'QC offices']);
        $this->project($mis, $infra, ['allotment_class' => 'co', 'description' => 'Laptop', 'quantity' => 41, 'unit_cost' => '100000', 'location_id' => $this->location('BCD')]);
        $this->project($mis, $infra, ['allotment_class' => 'semi', 'estimated_budget' => '864300', 'location_id' => $this->location('QC')]);
        $this->line($mis, $infra, 'TRV', '100000', 'QC');
        $this->line($mis, $infra, 'TRV', '70000', 'BCD');

        // Draft PPMPs' projects are left out unless asked for; the Budget Proposal's PAPs and budget lines are there
        $withoutDrafts = $this->reports->proposal($this->ppspd, 2027)['totals'];
        $this->assertSame([0, 22500000 + 17000000], [$withoutDrafts['co'], $withoutDrafts['mooe']]);
        $report = $this->reports->proposal($this->ppspd, 2027, drafts: true);

        // Office of the Manager, then PPPD with its MIS section (b. has no PAPs here, so MIS is "a.")
        $this->assertSame(['OTM', 'PPPD'], collect($report['blocks'])->map(fn ($b) => $b['office']->acronym)->all());
        $this->assertSame(22500000, $report['blocks'][0]['totals']['mooe']);
        $section = $report['blocks'][1]['sections'][0];
        $this->assertSame(['a', 'MIS'], [$section['letter'], $section['office']->acronym]);

        $pap = $section['paps'][0];
        $this->assertSame(306000000 + 410000000, $pap['co']);
        $this->assertSame(86430000 + 17000000, $pap['mooe']);
        $this->assertSame([$this->cls('SEMI') => 86430000, $this->cls('TRV') => 17000000], $pap['mooeByClass']);
        // MOOE and CO per location, by name: Bacolod City, Quezon City
        $this->assertSame(['Bacolod City', 'Quezon City'], $pap['mooeByLocation']->map(fn ($l) => $l['location']->name)->all());
        $this->assertSame(86430000 + 10000000, $pap['mooeByLocation'][1]['total']);
        $this->assertSame([410000000, 306000000], $pap['coByLocation']->pluck('total')->all());

        // Form B columns: the classes used, in their order
        $this->assertSame(['TRV', 'SEMI', 'REP'], $report['expenseColumns']->pluck('code')->all());
        $this->assertFalse($report['hasUnclassified']);
        $this->assertSame(716000000, $report['totals']['co']);
        $this->assertSame(22500000 + 103430000, $report['totals']['mooe']);
    }

    public function test_personnel_services_is_its_own_setting_with_history(): void
    {
        $officer = User::factory()->create();
        $officer->givePermissionTo(Permission::findOrCreate('manage budget'));
        $this->actingAs($officer);
        $base = ['department_id' => $this->ppspd->id, 'fiscal_year' => 2027, 'fund_group' => 'regular'];

        // Set without any CO + MOOE allocation
        $this->postJson(route('procurement.budget.personnel-services'), $base + ['amount' => '23,475,560'])->assertOk();
        $this->assertSame(0, \App\Models\Procurement\BudgetAllocation::count());
        $ps = \App\Models\Procurement\PersonnelService::sole();
        $this->assertSame('23475560.00', $ps->amount);

        // A change needs a reason, and is kept in its history
        $this->postJson(route('procurement.budget.personnel-services'), $base + ['amount' => '24,000,000'])->assertStatus(422)->assertJsonFragment(['status' => 'error']);
        $this->postJson(route('procurement.budget.personnel-services'), $base + ['amount' => '24,000,000', 'reason' => 'New plantilla positions'])->assertOk();
        $this->assertSame([['23475560.00', '24000000.00', 'New plantilla positions'], [null, '23475560.00', null]],
            $ps->history()->get()->map(fn ($h) => [$h->old_amount, $h->new_amount, $h->reason])->all());

        // It does not limit the PPMPs: no CO + MOOE allocation, so they are not checked
        [$ppmp, $pap, $staff] = $this->draft($this->research);
        $this->project($ppmp, $pap, ['estimated_budget' => '30000000']);
        $this->ppmps->submit($ppmp->fresh(), $staff);

        $this->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()->assertSee('24,000,000.00')->assertSee('New plantilla positions');
        // Not a department: refused
        $this->postJson(route('procurement.budget.personnel-services'), ['department_id' => $this->mis->id] + $base + ['amount' => '1'])->assertStatus(422);
    }

    public function test_breakdown_takes_ps_from_its_setting_and_co_mooe_from_the_ppmps(): void
    {
        $officer = User::factory()->create();
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '500000', $officer);
        $this->budget->savePersonnelServices($this->ppspd, 2027, FundGroup::Regular, '23,475,560', $officer);

        [$ppmp, $pap, $staff] = $this->draft($this->research);
        $this->project($ppmp, $pap, ['allotment_class' => 'co', 'estimated_budget' => '200000']);
        $this->line($ppmp, $pap, 'TRN', '150000');
        $this->ppmps->submit($ppmp->fresh(), $staff);

        $row = $this->reports->breakdown(2027)['regular']['rows']->sole();
        $this->assertSame($this->ppspd->id, $row['department']->id);
        $this->assertSame([2347556000, 20000000, 15000000, 2347556000 + 35000000], [$row['ps'], $row['co'], $row['mooe'], $row['total']]);
        $this->assertSame(50000000, $row['allocated']);
        $this->assertTrue($this->reports->breakdown(2027)['sida']['rows']->isEmpty());
    }

    public function test_performance_targets_are_set_on_the_budget_proposal_and_print_on_form_a(): void
    {
        [$ppmp, $pap, $staff] = $this->draft($this->research, 'Administrative Management');
        $this->line($ppmp, $pap, 'TRV', '4257700');
        $budgetPap = $pap->budgetPap;
        $proposal = $budgetPap->proposal;
        $this->proposals()->savePerformance($budgetPap, [
            ['description' => 'Implementation of laws, rules and regulations', 'indicator' => '% of issuances reviewed', 'q1' => '100%', 'q2' => '100%', 'q3' => '100%', 'q4' => '100%', 'total' => '100%'],
            ['description' => 'Performing SRA Committee Chairmanship', 'indicator' => '% of committee meetings attended', 'q1' => '100%', 'total' => '100%'],
            ['description' => '', 'indicator' => '% of NOA and NTP approved', 'q1' => '100%'],   // same activity as above
            ['indicator' => '', 'q1' => '9'],   // no indicator: dropped
            ['description' => 'Review and submission of SPMS', 'indicator' => 'No. of SPMS submitted for approval', 'q1' => '1'],
        ]);
        $this->assertSame(['Implementation of laws, rules and regulations', 'Performing SRA Committee Chairmanship', null, 'Review and submission of SPMS'], $budgetPap->indicators()->pluck('description')->all());

        // Its page, from the Budget Proposal; only its office edits it
        $staff->givePermissionTo(Permission::findOrCreate('manage budget proposals'));
        $this->actingAs($staff)->get(route('procurement.budget-proposals.performance', [$proposal, $budgetPap]))->assertOk()
            ->assertSee('No. of SPMS submitted for approval')->assertSee('Back to the FY 2027 Budget Proposal');
        $this->postJson(route('procurement.budget-proposals.performance.store', [$proposal, $budgetPap]), [
            'indicators' => $budgetPap->indicators()->get()->map(fn ($i) => $i->only(['description', 'indicator', 'q1', 'q2', 'q3', 'q4', 'total']))->all(),
        ])->assertOk();
        $misStaff = User::factory()->create(['office_id' => $this->mis->id]);
        $misStaff->givePermissionTo(Permission::findOrCreate('manage budget proposals'));
        $this->actingAs($misStaff)->postJson(route('procurement.budget-proposals.performance.store', [$proposal, $budgetPap]), ['indicators' => []])->assertForbidden();
        $this->assertSame(4, $budgetPap->indicators()->count());

        // Form A: each activity's description merged over its own indicators; budget spread over the quarters
        $officer = User::factory()->create();
        $officer->givePermissionTo(Permission::findOrCreate('manage budget'));
        $html = $this->actingAs($officer)->get(route('procurement.budget.cob.proposal', [$this->ppspd, 'fy' => 2027, 'drafts' => 1, 'show' => 'performance']))
            ->assertOk()->assertSee('Performance Contract')
            ->assertSeeInOrder(['Administrative Management', 'Implementation of laws, rules and regulations', 'Performing SRA Committee Chairmanship', '% of committee meetings attended', '% of NOA and NTP approved', 'Review and submission of SPMS'])
            ->assertSee('1,064,425')->assertSee('4,257,700')->getContent();
        $this->assertMatchesRegularExpression('/<td rowspan="2"[^>]*>Performing SRA Committee Chairmanship<\/td>/', $html);
        $this->assertMatchesRegularExpression('/<td rowspan="4">Administrative Management<\/td>/', $html);
    }

    public function test_signatories_print_from_settings_and_the_department(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole(\Spatie\Permission\Models\Role::findOrCreate('Super Admin'));
        $evaluator = User::factory()->create(['fname' => 'Eva', 'lname' => 'Luator', 'designation' => 'Budget Officer IV'])->fresh();
        $approver = User::factory()->create(['fname' => 'Board', 'lname' => 'Chairman'])->fresh();
        $manager = User::factory()->create(['fname' => 'Dept', 'lname' => 'Manager'])->fresh();
        $head = User::find($this->ppspd->head_user_id);

        $this->actingAs($admin)->postJson(route('procurement.officials.cob'), ['evaluated_1' => $evaluator->id, 'approved' => $approver->id])->assertOk();

        // Not set per department: the department head submits
        $this->get(route('procurement.budget.cob.proposal', [$this->ppspd, 'fy' => 2027, 'show' => 'summary']))
            ->assertOk()->assertSee($head->fullname)->assertSee($evaluator->fullname)->assertSee('Budget Officer IV')->assertSee($approver->fullname);

        // Set on the department's Budget Proposal page
        $proposal = $this->proposals()->forDepartment($this->ppspd, 2027);
        $this->postJson(route('procurement.budget-proposals.submitters', $proposal), ['users' => [$manager->id, $evaluator->id]])->assertOk();
        $this->postJson(route('procurement.budget-proposals.submitters', $proposal), ['users' => [1, 2, 3]])->assertStatus(422);
        $this->get(route('procurement.budget.cob.proposal', [$this->ppspd, 'fy' => 2027, 'show' => 'summary']))
            ->assertOk()->assertSee($manager->fullname)->assertDontSee($head->fullname);
        $this->get(route('procurement.budget.cob.breakdown', ['fy' => 2027]))->assertOk()->assertSee($approver->fullname);

        // Rows as on the form: Submitted by; the two evaluators side by side; Recommended by with Approved by
        $html = $this->get(route('procurement.budget.cob.proposal', [$this->ppspd, 'fy' => 2027, 'show' => 'summary']))->getContent();
        $rows = array_slice(explode('class="sig-row"', $html), 1);
        $this->assertCount(3, $rows);
        $this->assertSame(2, substr_count($rows[1], 'Evaluated by:'));
        $this->assertStringContainsString('Recommended by:', $rows[2]);
        $this->assertStringContainsString('Approved (by the Authority of the Sugar Board):', $rows[2]);
    }

    public function test_whole_organization_prints_the_breakdown_and_every_department_with_ppmps(): void
    {
        $officer = User::factory()->create();
        $officer->givePermissionTo(Permission::findOrCreate('manage budget'));
        $legal = Office::create(['code' => '04000', 'acronym' => 'LEGAL', 'name' => 'LEGAL DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular']);
        Office::create(['code' => '09000', 'acronym' => 'GAD', 'name' => 'GENDER AND DEVELOPMENT', 'type' => 'department', 'budget_fund' => 'regular']);   // no PPMP

        // Budget lines on their Budget Proposals (printed whatever the PPMPs' status)
        [$ppmp, $pap] = $this->draft($this->mis, 'ICT Infrastructure Management');
        $this->line($ppmp, $pap, 'GSV', '1320000');
        [$lppmp, $lpap] = $this->draft($legal, 'Legal Services');
        $this->line($lppmp, $lpap, 'TRV', '50000');

        $html = $this->actingAs($officer)->get(route('procurement.budget.cob.organization', ['fy' => 2027]))->assertOk()
            ->assertSeeInOrder(['Breakdown per Office / Department', 'FY 2027 Budget Proposal', 'LEGAL DEPARTMENT', 'FY 2027 Budget Proposal', 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT'])
            ->assertSee('ICT Infrastructure Management')->assertSee('Legal Services')->assertSee('Form A')->assertSee('Form C')
            ->assertDontSee('GENDER AND DEVELOPMENT')->getContent();
        $this->assertSame(2, substr_count($html, '<div class="form-tag">Summary</div>'));

        // Only the summaries; only the breakdown
        $this->get(route('procurement.budget.cob.organization', ['fy' => 2027, 'show' => 'summary']))->assertOk()
            ->assertSee('Legal Services')->assertDontSee('Form A')->assertDontSee('Breakdown per Office / Department</span>', false);
        $this->get(route('procurement.budget.cob.organization', ['fy' => 2027, 'show' => 'breakdown']))->assertOk()
            ->assertSee('Breakdown per Office / Department')->assertDontSee('FY 2027 Budget Proposal<br>', false);
    }

    public function test_ppmp_page_groups_mooe_projects_by_expense_class(): void
    {
        [$ppmp, $pap, $staff] = $this->draft($this->mis);
        $this->project($ppmp, $pap, ['description' => 'Service A', 'expense_class_id' => $this->cls('GSV'), 'estimated_budget' => '20000']);
        $this->project($ppmp, $pap, ['description' => 'Paper B', 'expense_class_id' => $this->cls('OFS'), 'estimated_budget' => '5000']);
        $this->project($ppmp, $pap, ['description' => 'Service C', 'expense_class_id' => $this->cls('GSV'), 'estimated_budget' => '10000']);
        $this->project($ppmp, $pap, ['description' => 'Filing cabinet', 'allotment_class' => 'semi', 'estimated_budget' => '30000']);

        $staff->givePermissionTo(Permission::findOrCreate('manage ppmp'));
        $this->actingAs($staff)->get(route('procurement.ppmp.show', $ppmp))->assertOk()
            ->assertSeeInOrder(['Filing cabinet', 'Office Supplies Expenses', '5,000.00', 'Paper B', 'General Services Expenses', '30,000.00', 'Service A', 'Service C'])
            ->assertDontSee('Semi-Expendable Expenses');   // one class: the Semi-Expendable row already says it
    }

    public function test_the_printouts_and_budget_line_form_open(): void
    {
        $officer = User::factory()->create();
        $officer->givePermissionTo(Permission::findOrCreate('manage budget'));
        [$ppmp, $pap, $staff] = $this->draft($this->mis, 'ICT Infrastructure Management');
        $this->project($ppmp, $pap, ['allotment_class' => 'co', 'description' => 'Server', 'quantity' => 2, 'unit_cost' => '450000', 'justification' => 'For the new data center']);
        $this->line($ppmp, $pap, 'GSV', '1320000', 'QC');
        $this->budget->save($this->ppspd, 2027, FundGroup::Regular, '5000000', $officer);
        $this->budget->savePersonnelServices($this->ppspd, 2027, FundGroup::Regular, '1000', $officer);

        $this->actingAs($officer)->get(route('procurement.budget.cob.breakdown', ['fy' => 2027, 'drafts' => 1]))
            ->assertOk()->assertSee('Breakdown per Office / Department')->assertSee('PPSPD')->assertSee('900,000');
        $this->get(route('procurement.budget.cob.proposal', [$this->ppspd, 'fy' => 2027, 'drafts' => 1]))
            ->assertOk()->assertSee('27-05012-01')->assertSee('ICT Infrastructure Management')->assertSee('General Services Expenses')
            ->assertSee('Quezon City')->assertSee('Server')->assertSee('For the new data center')->assertSee('2,220,000');
        $this->get(route('procurement.budget.index', ['fy' => 2027]))->assertOk()->assertSee('COB Breakdown');

        // The Budget Proposal page: its PAPs, budget lines and print; the office adds budget lines there
        $proposal = $pap->budgetPap->proposal;
        $staff->givePermissionTo(Permission::findOrCreate('manage budget proposals'));
        $this->actingAs($staff)->get(route('procurement.budget-proposals.show', $proposal))->assertOk()
            ->assertSee('ICT Infrastructure Management')->assertSee('GSV line')->assertSee('900,000.00');
        $this->get(route('procurement.budget-proposals.print', $proposal))->assertOk()->assertSee('ICT Infrastructure Management')->assertSee('Form C');
        $this->get(route('procurement.budget-proposals.lines.entry', [$proposal, 'pap_id' => $pap->budget_pap_id]))->assertOk()->assertSee('Add Budget Line');
        $this->postJson(route('procurement.budget-proposals.lines.store', $proposal), [
            'budget_pap_id' => $pap->budget_pap_id, 'expense_class_id' => $this->cls('TRV'), 'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'description' => 'Travel', 'amount' => '1,500.50',
        ])->assertOk();
        $this->assertSame('1500.50', $pap->budgetPap->budgetLines()->where('description', 'Travel')->value('amount'));

        // The PPMP page no longer has budget lines; the project form has the COB fields
        $staff->givePermissionTo(Permission::findOrCreate('manage ppmp'));
        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertDontSee('Budget only (not procured)')->assertDontSee('GSV line');
        $this->get(route('procurement.ppmp.items.entry', $ppmp))->assertOk()->assertSee('Expense Class')->assertSee('Justification');
    }
}
