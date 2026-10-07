<?php

namespace Tests\Feature\Procurement;

use App\Enums\ProjectType;
use App\Enums\RequestKind;
use App\Enums\RequestStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use App\Services\Procurement\PurchaseRequestService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class PurchaseRequestTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected PurchaseRequestService $requests;
    protected Office $section;
    protected User $head;
    protected User $staff;
    protected Ppmp $ppmp;
    protected PpmpItem $hdd;
    protected PpmpItem $ram;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        Carbon::setTestNow('2026-06-25 09:00:00');

        $this->ppmps = app(PpmpService::class);
        $this->requests = app(PurchaseRequestService::class);

        $this->head = User::factory()->create(['is_activated' => 1, 'fname' => 'Digna', 'minitial' => null, 'lname' => 'Gonzales', 'designation' => 'Manager III']);
        $department = Office::create(['type' => 'department', 'acronym' => 'PPSPD', 'name' => 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'budget_fund' => 'regular']);
        $division = Office::create(['type' => 'division', 'code' => '05010', 'acronym' => 'PPPD', 'name' => 'Planning, Policy and Programming Division', 'parent_id' => $department->id, 'head_user_id' => $this->head->id]);
        $this->section = Office::create(['type' => 'section', 'code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS Section', 'parent_id' => $division->id]);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section->id, 'designation' => 'Information Systems Analyst III']);
        $this->staff->givePermissionTo(['manage requests', 'manage ppmp']);
        $this->head->givePermissionTo(['manage requests', 'manage ppmp']);

        $this->ppmp = $this->ppmps->create($this->section, 2026, $this->staff);
        $pap = $this->ppmps->addPap($this->ppmp, '26-05012-51', 'Systems Development and Maintenance');
        $this->hdd = $this->ppmps->addItem($this->ppmp, $this->project($pap->id, 'Hard Drive (4 TB)', 8, '5000'));
        $this->ram = $this->ppmps->addItem($this->ppmp, $this->project($pap->id, 'RAM 16 GB', 10, '3000'));
        $this->ppmps->submit($this->ppmp, $this->staff);
        $this->ppmp = $this->ppmps->approve($this->ppmp->fresh(), $this->head);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function project(int $papId, string $description, int $quantity, string $unitCost): array
    {
        return [
            'ppmp_pap_id'         => $papId,
            'description'         => $description,
            'project_type'        => ProjectType::Goods,
            'quantity'            => $quantity,
            'unit_id'             => Unit::first()->id,
            'unit_cost'           => $unitCost,
            'quantity_size'       => "{$quantity} units",
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id'      => FundSource::where('code', 'COB')->value('id'),
            'allotment_class'     => 'mooe',
            'proc_start'          => '2026-02-01',
            'proc_end'            => '2026-04-01',
            'delivery_period'     => 'May 2026',
            'estimated_budget'    => (string) ($quantity * (float) $unitCost),
        ];
    }

    protected function signed(array $data = []): array
    {
        return $data + [
            'purpose'                  => 'Replacement of defective parts',
            'requested_by_name'        => 'Bren P. Tique',
            'requested_by_designation' => 'Information Systems Analyst III',
            'approved_by_name'         => 'Pablo Luis S. Azcona',
            'approved_by_designation'  => 'Administrator and CEO',
        ];
    }

    protected function draft(RequestKind $kind = RequestKind::Pr, array $data = []): PurchaseRequest
    {
        return $this->requests->create($kind, $this->ppmp->paps()->first(), $this->staff, $this->signed($data));
    }

    protected function line(PpmpItem $project, $quantity, $unitCost, array $extra = []): array
    {
        return $extra + ['ppmp_item_id' => $project->id, 'unit' => 'units', 'description' => $project->description, 'quantity' => $quantity, 'unit_cost' => $unitCost];
    }

    public function test_one_pr_holds_similar_items_and_submit_numbers_and_charges_it(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 4, '5000'));
        $this->requests->saveLine($pr, $this->line($this->ram, 2, '3000'));

        $this->assertEquals('26000.00', $pr->fresh()->total_amount);

        $pr = $this->requests->submit($pr->fresh(), $this->staff);

        $this->assertSame(RequestStatus::Submitted, $pr->status);
        $this->assertSame('2026-06-0001', $pr->request_no);
        $this->assertEquals('20000.00', $this->hdd->fresh()->committed_amount);
        $this->assertEquals('6000.00', $this->ram->fresh()->committed_amount);

        // Series runs through the year per kind; the month is the submit month
        Carbon::setTestNow('2026-07-02 10:00:00');
        $second = $this->draft();
        $this->requests->saveLine($second, $this->line($this->hdd, 1, '5000'));
        $this->assertSame('2026-07-0002', $this->requests->submit($second->fresh(), $this->staff)->request_no);

        $jr = $this->draft(RequestKind::Jr, ['jr_type' => 'repairs']);
        $this->requests->saveLine($jr, $this->line($this->ram, 1, '3000', ['nature_of_work' => 'Replace']));
        $this->assertSame('2026-07-0001', $this->requests->submit($jr->fresh(), $this->staff)->request_no);
    }

    public function test_partial_requests_cannot_go_over_the_ppmp_project(): void
    {
        $first = $this->draft();
        $this->requests->saveLine($first, $this->line($this->hdd, 6, '5000'));
        $this->requests->submit($first->fresh(), $this->staff);

        $second = $this->draft();
        $this->requests->saveLine($second, $this->line($this->hdd, 2, '5000'));   // exactly what is left

        try {
            $this->requests->saveLine($second, $this->line($this->hdd, 1, '5000'));
            $this->fail('A second line on the same project went over its budget.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString('Over the PPMP budget', $e->getMessage());
        }

        $this->requests->submit($second->fresh(), $this->staff);
        $this->assertEquals('40000.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_submit_needs_items_purpose_signatories_and_jr_type(): void
    {
        $pr = $this->requests->create(RequestKind::Pr, $this->ppmp->paps()->first(), $this->staff, []);
        $this->expectExceptionMessage('Add at least one item');
        $this->requests->submit($pr, $this->staff);
    }

    public function test_jr_needs_a_type(): void
    {
        $jr = $this->draft(RequestKind::Jr);
        $this->requests->saveLine($jr, $this->line($this->hdd, 1, '5000'));

        $this->expectExceptionMessage('JR type');
        $this->requests->submit($jr->fresh(), $this->staff);
    }

    public function test_revision_keeps_number_and_signatories_and_replaces_the_charges(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 8, '5000'));   // the whole project
        $pr = $this->requests->submit($pr->fresh(), $this->staff);

        $rev = $this->requests->revise($pr, $this->staff);
        $this->assertSame(RequestStatus::Draft, $rev->status);
        $this->assertSame(1, $rev->revision);
        $this->assertSame('2026-06-0001', $rev->request_no);
        $this->assertSame('Pablo Luis S. Azcona', $rev->approved_by_name);
        $this->assertCount(1, $rev->items);

        // The revision may use what the original holds: lower the quantity, add RAM
        $this->requests->saveLine($rev, $this->line($this->hdd, 5, '5000'), $rev->items->first());
        $this->requests->saveLine($rev, $this->line($this->ram, 3, '3000'));

        $rev = $this->requests->submit($rev->fresh(), $this->staff);

        $this->assertSame(RequestStatus::Superseded, $pr->fresh()->status);
        $this->assertSame('2026-06-0001', $rev->request_no);
        $this->assertSame('PR 2026-06-0001 Rev. 1', $rev->title());
        $this->assertEquals('25000.00', $this->hdd->fresh()->committed_amount);
        $this->assertEquals('9000.00', $this->ram->fresh()->committed_amount);
    }

    public function test_only_one_open_revision(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 1, '5000'));
        $pr = $this->requests->submit($pr->fresh(), $this->staff);
        $this->requests->revise($pr, $this->staff);

        $this->expectExceptionMessage('already being prepared');
        $this->requests->revise($pr->fresh(), $this->staff);
    }

    public function test_cancel_gives_the_budget_back(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 4, '5000'));
        $pr = $this->requests->submit($pr->fresh(), $this->staff);

        $this->requests->cancel($pr, $this->staff, 'Items donated');

        $this->assertSame(RequestStatus::Cancelled, $pr->fresh()->status);
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_submitted_request_is_locked(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 1, '5000'));
        $pr = $this->requests->submit($pr->fresh(), $this->staff);

        $this->expectException(ProcurementException::class);
        $this->requests->saveLine($pr, $this->line($this->hdd, 1, '5000'));
    }

    public function test_charges_follow_a_ppmp_amendment(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 2, '5000'));
        $pr = $this->requests->submit($pr->fresh(), $this->staff);

        $v2 = $this->ppmps->amend($this->ppmp, $this->staff);
        $this->ppmps->submit($v2->fresh(), $this->staff);
        $this->ppmps->approve($v2->fresh(), $this->head);
        $newHdd = $v2->items()->where('line_uuid', $this->hdd->line_uuid)->first();
        $this->assertEquals('10000.00', $newHdd->committed_amount);

        // A new request started from the old PAP lists the amended PPMP's projects
        $next = $this->requests->create(RequestKind::Pr, $v2->paps()->first(), $this->staff, $this->signed());
        $this->assertTrue($this->requests->availableLines($next)->contains('id', $newHdd->id));

        $this->requests->cancel($pr, $this->staff, 'Re-done');
        $this->assertEquals('0.00', $newHdd->fresh()->committed_amount);
    }

    public function test_other_offices_cannot_prepare_or_open_requests(): void
    {
        $outsider = User::factory()->create(['is_activated' => 1, 'office_id' => Office::create(['code' => '09000', 'name' => 'GAD'])->id]);
        $outsider->givePermissionTo('manage requests');

        $this->expectException(ProcurementException::class);
        $this->requests->create(RequestKind::Pr, $this->ppmp->paps()->first(), $outsider, []);
    }

    public function test_pages_workflow_and_print(): void
    {
        $this->actingAs($this->staff);
        $pap = $this->ppmp->paps()->first();

        $this->get(route('procurement.requests.index'))->assertOk()->assertSee('Purchase Request');
        $this->get(route('procurement.requests.entry', ['kind' => 'jr']))->assertOk()->assertSee('26-05012-51');

        $url = $this->postJson(route('procurement.requests.store'), ['kind' => 'pr', 'ppmp_pap_id' => $pap->id, 'purpose' => 'Upgrade of workstations'])
            ->assertOk()->json('url');
        $pr = PurchaseRequest::latest('id')->first();
        $this->assertSame('Digna Gonzales', $pr->requested_by_name);   // defaults to the office head

        $this->get($url)->assertOk()->assertSee('Upgrade of workstations');
        $this->get(route('procurement.requests.lines.entry', $pr))->assertOk()->assertSee('Hard Drive (4 TB)');

        $this->postJson(route('procurement.requests.lines.store', $pr), $this->line($this->hdd, 9, '5000'))
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Over the PPMP budget'));
        $this->postJson(route('procurement.requests.lines.store', $pr), $this->line($this->hdd, 4, '5000', ['stock_no' => '3629', 'specifications' => 'royal kludge']))
            ->assertOk();

        $this->postJson(route('procurement.requests.submit', $pr), $this->signed(['approved_by_name' => 'Admin']))->assertOk();
        $pr->refresh();
        $this->assertSame('2026-06-0001', $pr->request_no);
        $this->assertSame('Admin', $pr->approved_by_name);

        // Signatories can still be set from the print dialog after submit
        $this->postJson(route('procurement.requests.signatories', $pr), $this->signed())->assertOk();

        $this->get(route('procurement.requests.print', $pr))->assertOk()
            ->assertSee('PURCHASE REQUEST')->assertSee('REGULAR PROCUREMENT')->assertSee('2026-06-0001')
            ->assertSee('PPSPD')->assertSee('PLANNING, POLICY AND PROGRAMMING DIVISION - MIS SECTION')
            ->assertSee('26-05012-51')->assertSee('20,000.00')->assertSee('PABLO LUIS S. AZCONA', false)
            ->assertSee('FM-AFD-PPS-003');

        $this->postJson(route('procurement.requests.revise', $pr))->assertOk();
        $this->postJson(route('procurement.requests.cancel', $pr), ['reason' => ''])->assertStatus(422);
    }

    public function test_jr_print_has_the_certification_and_abc(): void
    {
        $jr = $this->draft(RequestKind::Jr, ['jr_type' => 'repairs']);
        $this->requests->saveLine($jr, $this->line($this->ram, 2, '3000', ['nature_of_work' => 'Replacement of memory modules']));
        $jr = $this->requests->submit($jr->fresh(), $this->staff);

        $this->actingAs($this->staff)->get(route('procurement.requests.print', $jr))->assertOk()
            ->assertSee('JOB REQUEST')->assertSee('J.R. No.')->assertSee('CERTIFICATION')->assertSee('ABC:')
            ->assertSee('Replacement of memory modules')->assertSee('Repairs and Maintenance')->assertSee('FM-AFD-PPS-001');
    }

    public function test_outsider_cannot_view_a_request(): void
    {
        $pr = $this->draft();
        $outsider = User::factory()->create(['is_activated' => 1, 'office_id' => Office::create(['code' => '09000', 'name' => 'GAD'])->id]);
        $outsider->givePermissionTo('manage requests');

        $this->actingAs($outsider)->get(route('procurement.requests.show', $pr))->assertForbidden();
        $this->actingAs($this->head)->get(route('procurement.requests.show', $pr))->assertOk();   // head of the division above
    }
}
