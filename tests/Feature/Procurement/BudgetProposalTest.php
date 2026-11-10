<?php

namespace Tests\Feature\Procurement;

use App\Enums\BudgetProposalStatus;
use App\Models\Procurement\BudgetProposal;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

/**
 * Budget Proposal pages: the department's offices write their PAP titles; it is submitted; the evaluator
 * gives the codes and evaluates it (or returns it). The PPMPs pick their PAPs from it.
 */
class BudgetProposalTest extends TestCase
{
    use RefreshDatabase;

    protected Office $ppspd;
    protected Office $mis;
    protected Office $research;
    protected User $misStaff;
    protected User $researchStaff;
    protected User $evaluator;
    protected User $outsider;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(ProcurementLookupSeeder::class);

        $this->ppspd = Office::create(['acronym' => 'PPSPD', 'name' => 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular', 'is_consolidating' => true,
            'head_user_id' => User::factory()->create()->id]);
        $pppd = Office::create(['code' => '05010', 'acronym' => 'PPPD', 'name' => 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'type' => 'division', 'parent_id' => $this->ppspd->id]);
        $this->research = Office::create(['code' => '05011', 'acronym' => 'PPRS', 'name' => 'PLANNING AND POLICY RESEARCH SECTION', 'type' => 'section', 'parent_id' => $pppd->id]);
        $this->mis = Office::create(['code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS SECTION', 'type' => 'section', 'parent_id' => $pppd->id]);
        $legal = Office::create(['code' => '04000', 'acronym' => 'LEGAL', 'name' => 'LEGAL DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular']);

        foreach (['manage budget proposals', 'evaluate budget proposals', 'manage ppmp'] as $perm) {
            Permission::findOrCreate($perm);
        }
        $this->misStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->mis->id]);
        $this->researchStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->research->id]);
        $this->outsider = User::factory()->create(['is_activated' => 1, 'office_id' => $legal->id]);
        foreach ([$this->misStaff, $this->researchStaff, $this->outsider] as $user) {
            $user->givePermissionTo(['manage budget proposals', 'manage ppmp']);
        }
        $this->evaluator = User::factory()->create(['is_activated' => 1]);
        $this->evaluator->givePermissionTo('evaluate budget proposals');
    }

    public function test_offices_write_their_paps_submit_and_the_evaluator_codes_and_evaluates(): void
    {
        // The list shows the user's own department; it is started from there
        $this->actingAs($this->misStaff)->get(route('procurement.budget-proposals.index', ['fy' => 2027]))->assertOk()
            ->assertSee('PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT')->assertDontSee('LEGAL DEPARTMENT')->assertSee('Start');
        $url = $this->postJson(route('procurement.budget-proposals.open'), ['department_id' => $this->ppspd->id, 'fiscal_year' => 2027])->assertOk()->json('url');
        $proposal = BudgetProposal::sole();
        $this->assertSame(route('procurement.budget-proposals.show', $proposal), $url);
        $this->actingAs($this->outsider)->postJson(route('procurement.budget-proposals.open'), ['department_id' => $this->ppspd->id, 'fiscal_year' => 2028])->assertForbidden();
        $this->actingAs($this->outsider)->get(route('procurement.budget-proposals.show', $proposal))->assertForbidden();

        // Each office writes its own PAP titles
        $this->actingAs($this->misStaff)->get(route('procurement.budget-proposals.show', $proposal))->assertOk()->assertSee('MIS SECTION')->assertSee('Add PAP');
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->mis->id, 'title' => 'ICT Infrastructure Management'])->assertOk();
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->mis->id, 'title' => 'Systems Development and Maintenance'])->assertOk();
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->mis->id, 'title' => 'ICT Infrastructure Management'])->assertStatus(422);
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->research->id, 'title' => 'Not mine'])->assertStatus(422);
        $ict = $proposal->paps()->where('title', 'ICT Infrastructure Management')->sole();
        $systems = $proposal->paps()->where('title', 'Systems Development and Maintenance')->sole();
        $this->actingAs($this->researchStaff)->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->research->id, 'title' => 'Preparation & Review of SRA Plans'])->assertOk();
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['id' => $ict->id, 'title' => 'Renamed'])->assertForbidden();   // MIS's PAP
        $plans = $proposal->paps()->where('office_id', $this->research->id)->sole();

        // A budget line on MIS's PAP
        $this->actingAs($this->misStaff)->postJson(route('procurement.budget-proposals.lines.store', $proposal), [
            'budget_pap_id' => $systems->id, 'expense_class_id' => \App\Models\Procurement\ExpenseClass::where('code', 'TRV')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'), 'description' => 'Travel', 'amount' => '70,000',
        ])->assertOk();
        // The description is optional: the line shows as its expense class
        $this->postJson(route('procurement.budget-proposals.lines.store', $proposal), [
            'budget_pap_id' => $systems->id, 'expense_class_id' => \App\Models\Procurement\ExpenseClass::where('code', 'TRN')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'), 'amount' => '15,000',
        ])->assertOk();
        // Added last, shown with the other traveling line: grouped by expense class, with its subtotal
        $this->postJson(route('procurement.budget-proposals.lines.store', $proposal), [
            'budget_pap_id' => $systems->id, 'expense_class_id' => \App\Models\Procurement\ExpenseClass::where('code', 'TRV')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'), 'description' => 'Second trip', 'amount' => '30,000',
        ])->assertOk();
        $this->get(route('procurement.budget-proposals.show', $proposal))->assertOk()
            ->assertSeeInOrder(['Traveling Expenses', '100,000.00', 'Travel', '70,000.00', 'Second trip', '30,000.00', 'Training Expenses', '15,000.00']);

        // The PPMP picks a PAP (before any code) and uses it; a PAP in a PPMP cannot be removed
        $ppmps = app(PpmpService::class);
        $ppmp = $ppmps->create($this->mis, 2027, $this->misStaff);
        $this->get(route('procurement.ppmp.paps.entry', $ppmp))->assertOk()->assertSee('ICT Infrastructure Management')->assertDontSee('Preparation &amp; Review', false);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['budget_pap_id' => $ict->id])->assertOk();
        $this->postJson(route('procurement.budget-proposals.paps.destroy', $proposal), ['id' => $ict->id])->assertStatus(422);

        // Submitted by the department; locked
        $this->actingAs($this->evaluator)->postJson(route('procurement.budget-proposals.submit', $proposal))->assertForbidden();
        $this->actingAs($this->misStaff)->postJson(route('procurement.budget-proposals.submit', $proposal))->assertOk();
        $this->assertSame(BudgetProposalStatus::Submitted, $proposal->fresh()->status);
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['office_id' => $this->mis->id, 'title' => 'Late'])->assertStatus(422);
        $this->postJson(route('procurement.budget-proposals.codes', $proposal), ['codes' => [$ict->id => '27-05012-01']])->assertForbidden();

        // The evaluator: suggested codes (by office, in order), duplicates refused, evaluate once all have one
        $this->actingAs($this->evaluator)->get(route('procurement.budget-proposals.index', ['fy' => 2027]))->assertOk()->assertSee('LEGAL DEPARTMENT')->assertSee('Evaluate');
        $this->get(route('procurement.budget-proposals.show', $proposal))->assertOk()
            ->assertSee('value="27-05011-01"', false)->assertSee('value="27-05012-01"', false)->assertSee('value="27-05012-02"', false);
        $this->postJson(route('procurement.budget-proposals.codes', $proposal), ['codes' => [$ict->id => '27-05012-01', $systems->id => '27-05012-01']])->assertStatus(422);
        $this->postJson(route('procurement.budget-proposals.codes', $proposal), ['codes' => [$ict->id => '27-05012-02', $systems->id => '27-05012-01']])->assertOk();
        $this->postJson(route('procurement.budget-proposals.evaluate', $proposal))->assertStatus(422);   // PPRS's PAP has no code
        $this->postJson(route('procurement.budget-proposals.codes', $proposal), ['codes' => [$plans->id => '27-05011-01']])->assertOk();
        $this->postJson(route('procurement.budget-proposals.evaluate', $proposal))->assertOk();
        $this->assertSame(BudgetProposalStatus::Evaluated, $proposal->fresh()->status);

        // The code now shows on the PPMP
        $this->actingAs($this->misStaff)->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('PAP CODE: 27-05012-02 - ICT Infrastructure Management');

        // Returned to reopen it
        $this->actingAs($this->evaluator)->postJson(route('procurement.budget-proposals.return', $proposal), ['remarks' => ''])->assertStatus(422);
        $this->postJson(route('procurement.budget-proposals.return', $proposal), ['remarks' => 'Add the training line'])->assertOk();
        $this->actingAs($this->misStaff)->get(route('procurement.budget-proposals.show', $proposal))->assertOk()->assertSee('Add the training line');
        $this->postJson(route('procurement.budget-proposals.paps.store', $proposal), ['id' => $systems->id, 'title' => 'Systems Development'])->assertOk();
        $this->assertSame('27-05012-01', $systems->fresh()->code);
    }
}
