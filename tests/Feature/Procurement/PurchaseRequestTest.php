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
use Illuminate\Support\Facades\DB;
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
        $this->requests->saveLine($second, $this->line($this->hdd, 1, '6000'));   // a dearer unit: 1 unit and 4,000 left

        try {
            $this->requests->saveLine($second, $this->line($this->hdd, 1, '5000'));
            $this->fail('A second line on the same project went over its budget.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString('Over the PPMP budget', $e->getMessage());
        }

        $this->requests->saveLine($second, $this->line($this->hdd, 1, '4000'));   // exactly what is left

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
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Over the PPMP quantity'));
        $this->postJson(route('procurement.requests.lines.store', $pr), $this->line($this->hdd, 8, '6000'))
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

    public function test_remaining_quantity_counts_submitted_requests_only(): void
    {
        $draft = $this->draft();

        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 3, '5000'));
        $a = $this->requests->submit($a->fresh(), $this->staff);
        $this->assertSame(500, $this->requests->remainingQuantity($draft, $this->hdd));

        $b = $this->draft();
        $this->requests->saveLine($b, $this->line($this->hdd, 2, '5000'));
        $b = $this->requests->submit($b->fresh(), $this->staff);
        $this->assertSame(300, $this->requests->remainingQuantity($draft, $this->hdd));

        $this->requests->cancel($b, $this->staff, 'Not needed');
        $this->assertSame(500, $this->requests->remainingQuantity($draft, $this->hdd));

        // Another draft holds nothing until it is submitted
        $e = $this->draft();
        $this->requests->saveLine($e, $this->line($this->hdd, 1, '5000'));
        $this->assertSame(500, $this->requests->remainingQuantity($draft, $this->hdd));

        // A submitted revision replaces the request before it (superseded: no longer counted)
        $rev = $this->requests->revise($a, $this->staff);
        $this->requests->saveLine($rev, $this->line($this->hdd, 1, '5000'), $rev->items->first());
        $this->requests->submit($rev->fresh(), $this->staff);
        $this->assertSame(RequestStatus::Superseded, $a->fresh()->status);
        $this->assertSame(700, $this->requests->remainingQuantity($draft, $this->hdd));
    }

    public function test_remaining_quantity_within_the_request(): void
    {
        $draft = $this->draft();
        $first = $this->requests->saveLine($draft, $this->line($this->hdd, 2, '5000'));
        $this->requests->saveLine($draft, $this->line($this->hdd, 1, '5000'));

        $this->assertSame(500, $this->requests->remainingQuantity($draft, $this->hdd));
        $this->assertSame(700, $this->requests->remainingQuantity($draft, $this->hdd, $first));   // editing: its own 2 count back

        $hdd = $this->requests->availableLines($draft, $first)->firstWhere('id', $this->hdd->id);
        $this->assertSame(700, $hdd->remaining_hundredths);
        $this->assertSame(800, $hdd->planned_hundredths);
        $this->assertSame(4000000, $hdd->budget_cents);
    }

    public function test_revision_gets_back_the_quantity_it_replaces(): void
    {
        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 6, '5000'));
        $a = $this->requests->submit($a->fresh(), $this->staff);
        $rev = $this->requests->revise($a, $this->staff);

        $this->assertSame(200, $this->requests->remainingQuantity($rev, $this->hdd));
        $this->assertSame(800, $this->requests->remainingQuantity($rev, $this->hdd, $rev->items->first()));
        $this->assertSame(200, $this->requests->remainingQuantity($this->draft(), $this->hdd));

        // The revision may take the whole plan back, but no more
        $this->assertOverQuantity(fn () => $this->requests->saveLine($rev, $this->line($this->hdd, 9, '4000'), $rev->items->first()));
        $this->requests->saveLine($rev, $this->line($this->hdd, 8, '4000'), $rev->items->first());
        $this->requests->submit($rev->fresh(), $this->staff);
        $this->assertSame(RequestStatus::Superseded, $a->fresh()->status);
        $this->assertEquals('32000.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_a_lot_has_no_quantity_limit(): void
    {
        PpmpItem::whereKey($this->hdd->id)->update(['quantity' => null]);
        $draft = $this->draft();

        $this->assertNull($this->requests->remainingQuantity($draft, $this->hdd));
        $this->assertNull($this->requests->availableLines($draft)->firstWhere('id', $this->hdd->id)->planned_hundredths);
        $this->requests->saveLine($draft, $this->line($this->hdd, 20, '1000'));   // only the budget limits a lot

        $this->actingAs($this->staff);
        $this->get(route('procurement.requests.lines.entry', $draft))->assertOk()->assertSee('Lot · no quantity limit');
    }

    protected function assertOverQuantity(callable $save, string $message = 'Over the PPMP quantity'): void
    {
        try {
            $save();
            $this->fail('A line went over the planned quantity.');
        } catch (ProcurementException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_quantity_over_the_plan_is_blocked(): void
    {
        $draft = $this->draft();

        // 10 x 4,000 = 40,000 is within the budget, but 8 are planned
        $this->assertOverQuantity(fn () => $this->requests->saveLine($draft, $this->line($this->hdd, 10, '4000')),
            'Over the PPMP quantity of "Hard Drive (4 TB)": this line is 10 piece, only 8 piece is left on the project.');

        // Another line on the same project gets only what is left; editing counts the line's own quantity back
        $line = $this->requests->saveLine($draft, $this->line($this->hdd, 5, '4000'));
        $this->assertOverQuantity(fn () => $this->requests->saveLine($draft, $this->line($this->hdd, 4, '4000')));
        $this->requests->saveLine($draft, $this->line($this->hdd, 3, '4000'));
        $this->assertOverQuantity(fn () => $this->requests->saveLine($draft, $this->line($this->hdd, 6, '4000'), $line));
        $this->requests->saveLine($draft, $this->line($this->hdd, 5, '4000'), $line);
        $this->assertSame(0, $this->requests->remainingQuantity($draft, $this->hdd));
        $this->assertCount(2, $draft->items()->get());

        // The page refuses it too
        $response = $this->actingAs($this->staff)
            ->postJson(route('procurement.requests.lines.store', $draft), $this->line($this->ram, 11, '1000'))->assertStatus(422);
        $this->assertStringContainsString('Over the PPMP quantity of "RAM 16 GB"', $response->json('message'));
    }

    public function test_submit_refuses_a_draft_now_over_the_quantity(): void
    {
        $draft = $this->draft();
        $line = $this->requests->saveLine($draft, $this->line($this->hdd, 5, '4000'));   // 5 of 8 when saved

        $other = $this->draft();
        $this->requests->saveLine($other, $this->line($this->hdd, 4, '5000'));
        $this->requests->submit($other->fresh(), $this->staff);   // 4 left; the budget still covers the draft

        $this->assertOverQuantity(fn () => $this->requests->submit($draft->fresh(), $this->staff),
            'Over the PPMP quantity of "Hard Drive (4 TB)": requested 5 piece, only 4 piece is left.');
        $this->assertSame(RequestStatus::Draft, $draft->fresh()->status);
        $this->assertEquals('20000.00', $this->hdd->fresh()->committed_amount);   // the draft's charge was rolled back

        // Lowered to what is left, it goes through
        $this->requests->saveLine($draft, $this->line($this->hdd, 4, '4000'), $line);
        $this->requests->submit($draft->fresh(), $this->staff);
        $this->assertEquals('36000.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_the_ppmp_line_keeps_the_quantity_submitted_requests_hold(): void
    {
        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 3, '5000'));
        $this->requests->saveLine($a, $this->line($this->hdd, 2, '5000'));
        $a = $this->requests->submit($a->fresh(), $this->staff);
        $this->assertEquals('5.00', $this->hdd->fresh()->committed_quantity);

        // A revision swaps its quantity for the original's; cancelling gives it back
        $rev = $this->requests->revise($a, $this->staff);
        $this->requests->removeLine($rev->items()->first());
        $rev = $this->requests->submit($rev->fresh(), $this->staff);
        $this->assertEquals('2.00', $this->hdd->fresh()->committed_quantity);

        $this->requests->cancel($rev, $this->staff, 'Not needed');
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_quantity);
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_charging_refuses_what_the_line_no_longer_has(): void
    {
        // As if another request was submitted after this draft was checked: the charge itself refuses
        PpmpItem::whereKey($this->hdd->id)->update(['committed_quantity' => 7.5]);

        try {
            app(\App\Services\Procurement\PpmpBudgetService::class)->charge($this->hdd, '5000', '1');
            $this->fail('Charged more than the planned quantity.');
        } catch (ProcurementException $e) {
            $this->assertSame('Over the PPMP quantity of "Hard Drive (4 TB)": requested 1 piece, only 0.5 piece is left.', $e->getMessage());
        }

        $this->assertEquals('7.50', $this->hdd->fresh()->committed_quantity);
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_a_project_with_quantity_0_has_no_quantity_limit(): void
    {
        PpmpItem::whereKey($this->hdd->id)->update(['quantity' => 0]);
        $draft = $this->draft();

        $this->assertNull($this->requests->remainingQuantity($draft, $this->hdd));
        $this->requests->saveLine($draft, $this->line($this->hdd, 20, '1000'));
        $this->requests->submit($draft->fresh(), $this->staff);
        $this->assertEquals('20.00', $this->hdd->fresh()->committed_quantity);
    }

    public function test_an_amendment_cannot_plan_less_than_requests_hold(): void
    {
        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 4, '5000'));
        $this->requests->submit($a->fresh(), $this->staff);

        $v2 = $this->ppmps->amend($this->ppmp, $this->staff);
        $newHdd = $v2->items()->where('line_uuid', $this->hdd->line_uuid)->first();

        try {
            $this->ppmps->updateItem($newHdd, ['quantity' => 3, 'unit_cost' => '10000', 'estimated_budget' => '30000']);
            $this->fail('The amendment planned less than the requests hold.');
        } catch (ProcurementException $e) {
            $this->assertSame('Line "Hard Drive (4 TB)" quantity cannot go below the 4 piece already requested by PRs.', $e->getMessage());
        }

        $this->ppmps->updateItem($newHdd, ['quantity' => 4, 'unit_cost' => '5000', 'estimated_budget' => '20000']);
        $this->ppmps->submit($v2->fresh(), $this->staff);
        $this->ppmps->approve($v2->fresh(), $this->head);
        $this->assertEquals('4.00', $newHdd->fresh()->committed_quantity);
        $next = $this->requests->create(RequestKind::Pr, $v2->paps()->first(), $this->staff, $this->signed());
        $this->assertSame(0, $this->requests->remainingQuantity($next, $newHdd));
    }

    public function test_used_up_projects_cannot_be_picked(): void
    {
        $this->actingAs($this->staff);
        PpmpItem::whereKey($this->hdd->id)->update(['committed_quantity' => 9]);   // over-requested before quantities were enforced

        $this->get(route('procurement.requests.lines.entry', $this->draft()))->assertOk()
            ->assertSeeText('1 piece over')->assertSee('Fully requested')
            ->assertSee('data-id="' . $this->hdd->id . '" disabled', false)
            ->assertDontSee('data-id="' . $this->ram->id . '" disabled', false);
    }

    public function test_a_stale_copy_cannot_cancel_twice_or_edit_after_submit(): void
    {
        $pr = $this->draft();
        $this->requests->saveLine($pr, $this->line($this->hdd, 2, '5000'));
        $stale = $pr->fresh();   // loaded by a second tab before submit
        $submitted = $this->requests->submit($pr->fresh(), $this->staff);

        // The draft copy is checked again on the locked row
        $this->assertOverQuantity(fn () => $this->requests->saveLine($stale, $this->line($this->hdd, 1, '5000')), 'can no longer be changed');
        $this->assertCount(1, $submitted->items()->get());

        $copy = $submitted->fresh();
        $this->requests->cancel($submitted, $this->staff, 'Not needed');
        $this->assertOverQuantity(fn () => $this->requests->cancel($copy, $this->staff, 'Again'), 'is Cancelled');
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_quantity);
        $this->assertEquals('0.00', $this->hdd->fresh()->committed_amount);
    }

    public function test_pages_show_quantity_left(): void
    {
        $this->actingAs($this->staff);
        $pr = $this->draft();

        // Add Item opens on what is left to request; the page itself no longer lists it
        $this->get(route('procurement.requests.lines.entry', $pr))->assertOk()->assertSee('Left to request under')->assertSeeText('8 of 8 piece');

        $this->postJson(route('procurement.requests.lines.store', $pr), $this->line($this->hdd, 4, '5000'))->assertOk();
        $this->get(route('procurement.requests.lines.entry', $pr))->assertOk()->assertSeeText('4 of 8 piece');
        $this->get(route('procurement.requests.show', $pr))->assertOk()->assertDontSee('Left to request under')->assertDontSee('SAI No.');

        $this->get(route('procurement.requests.lines.entry', ['purchaseRequest' => $pr, 'project' => $this->ram->id]))
            ->assertOk()->assertSee('value="' . $this->ram->id . '" selected', false);
    }

    public function test_editing_after_a_ppmp_amendment_selects_the_amended_project(): void
    {
        $this->actingAs($this->staff);
        $draft = $this->draft();
        $draftLine = $this->requests->saveLine($draft, $this->line($this->hdd, 3, '5000'));
        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 2, '5000'));
        $a = $this->requests->submit($a->fresh(), $this->staff);

        $v2 = $this->ppmps->amend($this->ppmp, $this->staff);
        $this->ppmps->submit($v2->fresh(), $this->staff);
        $this->ppmps->approve($v2->fresh(), $this->head);
        $newHdd = $v2->items()->where('line_uuid', $this->hdd->line_uuid)->first();
        $this->assertNotSame($newHdd->id, $draftLine->ppmp_item_id);

        // The lines keep the old version's project id; the modal still picks the project (by line_uuid)
        $this->get(route('procurement.requests.lines.entry', ['purchaseRequest' => $draft, 'id' => $draftLine->id]))
            ->assertOk()->assertSee('value="' . $newHdd->id . '" selected', false)->assertSeeText('6 of 8 piece');

        $rev = $this->requests->revise($a, $this->staff);
        $this->get(route('procurement.requests.lines.entry', ['purchaseRequest' => $rev, 'id' => $rev->items->first()->id]))
            ->assertOk()->assertSee('value="' . $newHdd->id . '" selected', false)->assertSeeText('8 of 8 piece');
    }

    public function test_available_lines_take_the_same_queries_whatever_the_number_of_projects(): void
    {
        $a = $this->draft();
        $this->requests->saveLine($a, $this->line($this->hdd, 2, '5000'));
        $a = $this->requests->submit($a->fresh(), $this->staff);
        $rev = $this->requests->revise($a, $this->staff);
        $line = $rev->items()->first();

        DB::enableQueryLog();
        $lines = $this->requests->availableLines($rev, $line);
        $queries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertCount(2, $lines);
        $this->assertLessThanOrEqual(10, $queries);   // 9 now; 7 more per project before (20 here)
        $this->assertSame(800, $lines->firstWhere('id', $this->hdd->id)->remaining_hundredths);
        $this->assertSame(4000000, $lines->firstWhere('id', $this->hdd->id)->remaining_cents);
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

    public function test_a_visayas_request_uses_the_visayas_ppmp_of_the_same_office(): void
    {
        // The office also has an approved Visayas PPMP for the same year, with its own PAP
        $vis = $this->ppmps->create($this->section, 2026, $this->staff, \App\Enums\PpmpType::Final, null, \App\Enums\Region::Vis);
        $visPap = $this->ppmps->addPap($vis, $this->ppmps->suggestPapCode($vis), 'VIS ICT');
        $router = $this->ppmps->addItem($vis, $this->project($visPap->id, 'Router (VIS)', 4, '2500'));
        $this->ppmps->submit($vis, $this->staff);
        $this->ppmps->approve($vis->fresh(), $this->head);

        $pr = $this->requests->create(RequestKind::Pr, $visPap->fresh(), $this->staff, $this->signed());
        $lines = $this->requests->availableLines($pr);
        $this->assertSame(['Router (VIS)'], $lines->pluck('description')->all());

        $this->requests->saveLine($pr, $this->line($router, 3, '2500'));
        $this->assertEquals('7500.00', $pr->fresh()->total_amount);

        // The Luzon/Mindanao request still sees its own projects
        $lm = $this->draft();
        $this->assertContains('Hard Drive (4 TB)', $this->requests->availableLines($lm)->pluck('description')->all());
    }
}
