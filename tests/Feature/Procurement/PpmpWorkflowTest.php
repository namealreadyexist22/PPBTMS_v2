<?php

namespace Tests\Feature\Procurement;

use App\Enums\PpmpStatus;
use App\Enums\ProjectType;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\PpmpBudgetService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PpmpWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected PpmpBudgetService $budget;
    protected Office $office;
    protected User $staff;
    protected User $head;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);

        $this->ppmps = app(PpmpService::class);
        $this->budget = app(PpmpBudgetService::class);

        $this->head = User::factory()->create(['designation' => 'Regional Executive Director']);
        $this->office = Office::create(['code' => '01000', 'acronym' => 'ORED', 'name' => 'Office of the RED', 'head_user_id' => $this->head->id]);
        $this->staff = User::factory()->create(['office_id' => $this->office->id, 'designation' => 'Senior Agriculturist']);
    }

    protected function line(array $overrides = []): array
    {
        return array_merge([
            'description'         => 'Supply of office supplies',
            'project_type'        => ProjectType::Goods,
            'quantity_size'       => '1 lot',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id'      => FundSource::where('code', 'GAA')->value('id'),
            'proc_start'          => '2027-01-01',
            'proc_end'            => '2027-02-01',
            'delivery_period'     => 'March 2027',
            'estimated_budget'    => '100000.00',
        ], $overrides);
    }

    protected function approvedPpmp(): Ppmp
    {
        $ppmp = $this->ppmps->create($this->office, 2027, $this->staff);
        $this->ppmps->addItem($ppmp, $this->line());
        $this->ppmps->addItem($ppmp, $this->line(['description' => 'Laptops', 'estimated_budget' => '250000']));
        $this->ppmps->submit($ppmp, $this->staff);

        return $this->ppmps->approve($ppmp->fresh(), $this->head);
    }

    public function test_office_prepares_submits_and_head_approves(): void
    {
        $ppmp = $this->approvedPpmp();

        $this->assertSame(PpmpStatus::Approved, $ppmp->status);
        $this->assertSame('27-01000-01', $ppmp->ppmp_no);
        $this->assertEquals('350000.00', $ppmp->fresh()->total_budget);
        $this->assertEqualsCanonicalizing(['prepared', 'submitted', 'approved'], $ppmp->signatories()->pluck('role')->all());
        $this->assertSame('Senior Agriculturist', $ppmp->latestSignatory('submitted')->designation_snapshot);
    }

    public function test_only_office_head_can_approve(): void
    {
        $ppmp = $this->ppmps->create($this->office, 2027, $this->staff);
        $this->ppmps->addItem($ppmp, $this->line());
        $this->ppmps->submit($ppmp, $this->staff);

        $this->expectException(ProcurementException::class);
        $this->ppmps->approve($ppmp->fresh(), $this->staff);
    }

    public function test_returned_ppmp_can_be_edited_and_resubmitted(): void
    {
        $ppmp = $this->ppmps->create($this->office, 2027, $this->staff);
        $item = $this->ppmps->addItem($ppmp, $this->line());
        $this->ppmps->submit($ppmp, $this->staff);
        $this->ppmps->returnToOffice($ppmp->fresh(), $this->head, 'Split the lot');

        $this->ppmps->updateItem($item->fresh(), ['estimated_budget' => '80000']);
        $this->ppmps->submit($ppmp->fresh(), $this->staff);

        $this->assertSame(PpmpStatus::Submitted, $ppmp->fresh()->status);
        $this->assertEquals('80000.00', $ppmp->fresh()->total_budget);
    }

    public function test_approved_ppmp_is_locked(): void
    {
        $ppmp = $this->approvedPpmp();

        $this->expectException(ProcurementException::class);
        $this->ppmps->addItem($ppmp, $this->line());
    }

    public function test_one_ppmp_per_office_per_year(): void
    {
        $this->ppmps->create($this->office, 2027, $this->staff);

        $this->expectException(ProcurementException::class);
        $this->ppmps->create($this->office, 2027, $this->staff);
    }

    public function test_budget_charge_is_blocked_when_exceeding_available(): void
    {
        $line = $this->approvedPpmp()->items()->first();

        $this->budget->charge($line, '60000');
        $this->assertEquals('40000.00', $line->fresh()->availableBudget());

        $this->expectException(ProcurementException::class);
        $this->budget->charge($line, '40000.01');
    }

    public function test_cannot_charge_unapproved_ppmp(): void
    {
        $ppmp = $this->ppmps->create($this->office, 2027, $this->staff);
        $line = $this->ppmps->addItem($ppmp, $this->line());

        $this->expectException(ProcurementException::class);
        $this->budget->charge($line, '100');
    }

    public function test_amendment_carries_charges_and_supersedes_old_version(): void
    {
        $v1 = $this->approvedPpmp();
        $oldLine = $v1->items()->first();
        $this->budget->charge($oldLine, '30000');

        $v2 = $this->ppmps->amend($v1, $this->staff);
        $this->assertSame('27-01000-02', $v2->ppmp_no);

        // Charge made while the amendment is still being prepared.
        $this->budget->charge($oldLine, '10000');

        $newLine = $v2->items()->where('line_uuid', $oldLine->line_uuid)->first();
        $this->ppmps->updateItem($newLine, ['estimated_budget' => '150000']);
        $this->ppmps->submit($v2->fresh(), $this->staff);
        $this->ppmps->approve($v2->fresh(), $this->head);

        $this->assertSame(PpmpStatus::Superseded, $v1->fresh()->status);
        $this->assertEquals('40000.00', $newLine->fresh()->committed_amount);

        // Charging via the old line now lands on the current version.
        $current = $this->budget->charge($oldLine, '5000');
        $this->assertSame($newLine->id, $current->id);
        $this->assertEquals('105000.00', $current->availableBudget());
    }

    public function test_amendment_cannot_drop_budget_below_charges(): void
    {
        $v1 = $this->approvedPpmp();
        $oldLine = $v1->items()->first();
        $v2 = $this->ppmps->amend($v1, $this->staff);
        $newLine = $v2->items()->where('line_uuid', $oldLine->line_uuid)->first();
        $this->ppmps->updateItem($newLine, ['estimated_budget' => '20000']);
        $this->ppmps->submit($v2->fresh(), $this->staff);

        // PR charged on v1 after the amendment was submitted.
        $this->budget->charge($oldLine, '50000');

        try {
            $this->ppmps->approve($v2->fresh(), $this->head);
            $this->fail('Approval should have been blocked.');
        } catch (ProcurementException) {
            $this->assertSame(PpmpStatus::Approved, $v1->fresh()->status);
            $this->assertSame(PpmpStatus::Submitted, $v2->fresh()->status);
        }
    }

    public function test_release_returns_budget(): void
    {
        $line = $this->approvedPpmp()->items()->first();
        $this->budget->charge($line, '25000');
        $this->budget->release($line, '25000');

        $this->assertEquals('100000.00', $line->fresh()->availableBudget());
    }

    protected function ppspdWithSections(): array
    {
        $divisionHead = User::factory()->create(['designation' => 'Division Chief']);
        $sectionHead = User::factory()->create(['designation' => 'Section Chief']);
        $division = Office::create(['code' => '05000', 'acronym' => 'PPSPD', 'name' => 'Planning Division', 'head_user_id' => $divisionHead->id]);
        $planning = Office::create(['code' => '05001', 'acronym' => 'PPSPD-PS', 'name' => 'Planning Section', 'parent_id' => $division->id, 'head_user_id' => $sectionHead->id]);
        $special = Office::create(['code' => '05002', 'acronym' => 'PPSPD-SPS', 'name' => 'Special Project Section', 'parent_id' => $division->id]);

        return [$divisionHead, $sectionHead, $planning, $special];
    }

    public function test_section_ppmp_is_approved_by_division_head(): void
    {
        [$divisionHead, $sectionHead, $planning] = $this->ppspdWithSections();
        $staff = User::factory()->create(['office_id' => $planning->id]);

        $ppmp = $this->ppmps->create($planning, 2027, $staff);
        $this->ppmps->addItem($ppmp, $this->line());
        $this->ppmps->submit($ppmp, $staff);

        try {
            $this->ppmps->approve($ppmp->fresh(), $sectionHead);
            $this->fail('Section head must not approve.');
        } catch (ProcurementException) {
        }

        $this->ppmps->approve($ppmp->fresh(), $divisionHead);
        $this->assertSame(PpmpStatus::Approved, $ppmp->fresh()->status);
    }

    public function test_visibility_by_office_division_and_view_all(): void
    {
        [$divisionHead, , $planning, $special] = $this->ppspdWithSections();
        $planningStaff = User::factory()->create(['office_id' => $planning->id]);
        $specialStaff = User::factory()->create(['office_id' => $special->id]);

        $a = $this->ppmps->create($planning, 2027, $planningStaff);
        $b = $this->ppmps->create($special, 2027, $specialStaff);
        $c = $this->ppmps->create($this->office, 2027, $this->staff);

        $ids = fn (User $u) => Ppmp::visibleTo($u)->pluck('id')->sort()->values()->all();

        $this->assertSame([$a->id], $ids($planningStaff));
        $this->assertSame([$a->id, $b->id], $ids($divisionHead));
        $this->assertSame([$c->id], $ids($this->head));

        \Spatie\Permission\Models\Permission::create(['name' => Ppmp::VIEW_ALL_PERMISSION, 'guard_name' => 'web']);
        $bac = User::factory()->create();
        $bac->givePermissionTo(Ppmp::VIEW_ALL_PERMISSION);
        $this->assertSame([$a->id, $b->id, $c->id], $ids($bac));
    }

    public function test_user_can_only_create_for_own_office(): void
    {
        Office::create(['code' => '09000', 'name' => 'Other Office']);

        $this->assertSame([$this->office->id], Office::assignableTo($this->staff)->pluck('id')->all());
        $this->assertSame([], Office::assignableTo(User::factory()->create())->pluck('id')->all());
    }

    public function test_approver_is_nearest_head_above_at_any_depth(): void
    {
        $deputy = User::factory()->create();
        $manager = User::factory()->create();
        $top = Office::create(['code' => '06000', 'name' => 'Deputy Admin', 'head_user_id' => $deputy->id]);
        $dept = Office::create(['code' => '06010', 'name' => 'AFD-LM Manager III', 'parent_id' => $top->id, 'head_user_id' => $manager->id]);
        $division = Office::create(['code' => '06020', 'name' => 'GAD', 'parent_id' => $dept->id]);   // no head yet
        $section = Office::create(['code' => '06021', 'name' => 'HRRS', 'parent_id' => $division->id]);

        $this->assertSame($manager->id, $section->approverId());   // skips GAD (no head)
        $this->assertSame($manager->id, $division->approverId());
        $this->assertSame($deputy->id, $dept->approverId());
        $this->assertSame($deputy->id, $top->approverId());        // top level approves its own
    }

    public function test_extra_offices_are_for_prs_only(): void
    {
        $scp = Office::create(['code' => '11000', 'name' => 'SIDA-SCP']);
        $hrd = Office::create(['code' => '12000', 'name' => 'SIDA-HRD']);
        Office::create(['code' => '13000', 'name' => 'SIDA-FMR']);
        $bien = User::factory()->create(['office_id' => $scp->id]);
        $bien->offices()->attach($hrd);
        $hrdStaff = User::factory()->create(['office_id' => $hrd->id]);

        // PPMP: home office only
        $this->assertSame([$scp->id], Office::assignableTo($bien)->pluck('id')->all());
        $a = $this->ppmps->create($scp, 2027, $bien);
        $b = $this->ppmps->create($hrd, 2027, $hrdStaff);
        $this->ppmps->create($this->office, 2027, $this->staff);

        try {
            $this->ppmps->create($hrd, 2028, $bien);
            $this->fail('Bien must not create a PPMP for SIDA-HRD.');
        } catch (ProcurementException) {
        }

        // PR: home + extra offices; he can see SIDA-HRD's PPMP but not edit it
        $this->assertEqualsCanonicalizing([$scp->id, $hrd->id], $bien->prOfficeIds());
        $this->assertEqualsCanonicalizing([$a->id, $b->id], Ppmp::visibleTo($bien)->pluck('id')->all());
        $this->assertTrue($a->isEditableBy($bien));
        $this->assertFalse($b->isEditableBy($bien));
    }

    public function test_head_sees_ppmps_of_all_offices_below(): void
    {
        $manager = User::factory()->create();
        $dept = Office::create(['code' => '05000', 'name' => 'PPSPD Manager III', 'head_user_id' => $manager->id]);
        $division = Office::create(['code' => '05010', 'name' => 'PPPD', 'parent_id' => $dept->id]);
        $section = Office::create(['code' => '05011', 'name' => 'PPRS', 'parent_id' => $division->id]);

        $p1 = $this->ppmps->create($division, 2027, User::factory()->create(['office_id' => $division->id]));
        $p2 = $this->ppmps->create($section, 2027, User::factory()->create(['office_id' => $section->id]));
        $this->ppmps->create($this->office, 2027, $this->staff);

        $this->assertEqualsCanonicalizing([$p1->id, $p2->id], Ppmp::visibleTo($manager)->pluck('id')->all());
    }
}
