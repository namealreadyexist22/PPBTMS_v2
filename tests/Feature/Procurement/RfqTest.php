<?php

namespace Tests\Feature\Procurement;

use App\Enums\ProjectType;
use App\Enums\Region;
use App\Enums\RequestKind;
use App\Enums\RfqStatus;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Procurement\RfqService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class RfqTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected PurchaseRequestService $requests;
    protected RfqService $rfqs;
    protected User $staff;
    protected User $bac;
    protected Ppmp $ppmp;
    protected PpmpItem $hdd;
    protected PpmpItem $ram;
    protected PpmpItem $desktop;   // public bidding (CB), not SVP

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->ppmps = app(PpmpService::class);
        $this->requests = app(PurchaseRequestService::class);
        $this->rfqs = app(RfqService::class);

        $head = User::factory()->create(['is_activated' => 1]);
        $department = Office::create(['type' => 'department', 'acronym' => 'PPSPD', 'name' => 'PLANNING DEPARTMENT', 'budget_fund' => 'regular']);
        $division = Office::create(['type' => 'division', 'code' => '05010', 'acronym' => 'PPPD', 'name' => 'Planning Division', 'parent_id' => $department->id, 'head_user_id' => $head->id]);
        $section = Office::create(['type' => 'section', 'code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS Section', 'parent_id' => $division->id]);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $section->id]);
        $this->staff->givePermissionTo(['manage requests', 'manage ppmp']);
        $head->givePermissionTo(['manage requests', 'manage ppmp']);
        $this->bac = User::factory()->create(['is_activated' => 1, 'fname' => 'Ana', 'lname' => 'Santos', 'designation' => 'Head, BAC Secretariat']);
        $this->bac->givePermissionTo('manage rfq');

        $this->ppmp = $this->ppmps->create($section, 2027, $this->staff);
        $pap = $this->ppmps->addPap($this->ppmp, '27-05012-01', 'ICT Infrastructure');
        $this->hdd = $this->ppmps->addItem($this->ppmp, $this->project($pap->id, 'Hard Drive (4 TB)', 8, '5000', 'SVP'));
        $this->ram = $this->ppmps->addItem($this->ppmp, $this->project($pap->id, 'RAM 16 GB', 10, '3000', 'SVP'));
        $this->desktop = $this->ppmps->addItem($this->ppmp, $this->project($pap->id, 'Desktop Computer', 5, '60000', 'CB'));
        $this->ppmps->submit($this->ppmp, $this->staff);
        $this->ppmp = $this->ppmps->approve($this->ppmp->fresh(), $head);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function project(int $papId, string $description, int $quantity, string $unitCost, string $mode): array
    {
        return [
            'ppmp_pap_id' => $papId, 'description' => $description, 'project_type' => ProjectType::Goods, 'quantity' => $quantity,
            'unit_id' => Unit::first()->id, 'unit_cost' => $unitCost, 'quantity_size' => "{$quantity} units",
            'procurement_mode_id' => ProcurementMode::where('code', $mode)->value('id'), 'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'allotment_class' => 'mooe', 'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'delivery_period' => 'May 2027',
            'estimated_budget' => (string) ($quantity * (float) $unitCost),
        ];
    }

    /** A submitted PR with the given [project, quantity] lines. */
    protected function submittedPr(array $lines): PurchaseRequest
    {
        $pr = $this->requests->create(RequestKind::Pr, $this->ppmp->paps()->first(), $this->staff, [
            'purpose' => 'ICT equipment', 'requested_by_name' => 'Bren Tique', 'approved_by_name' => 'Pablo Azcona',
        ]);
        foreach ($lines as [$project, $qty]) {
            $this->requests->saveLine($pr, ['ppmp_item_id' => $project->id, 'unit' => 'piece', 'description' => $project->description, 'quantity' => $qty, 'unit_cost' => $project->unit_cost]);
        }

        return $this->requests->submit($pr->fresh(), $this->staff);
    }

    protected function draftRfq(): Rfq
    {
        return $this->rfqs->create($this->bac, Region::Lm, ['title' => 'ICT supplies']);
    }

    protected function refused(callable $action, string $message): void
    {
        try {
            $action();
            $this->fail("Expected: {$message}");
        } catch (ProcurementException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }

    public function test_one_rfq_combines_lines_of_several_prs_and_issuing_numbers_it(): void
    {
        $a = $this->submittedPr([[$this->hdd, 2], [$this->desktop, 1]]);
        $b = $this->submittedPr([[$this->ram, 4]]);
        $rfq = $this->draftRfq();

        $this->rfqs->addLines($rfq, $this->bac, [$a->items[0]->id, $b->items[0]->id]);
        $rfq->refresh();
        $this->assertEquals('22000.00', $rfq->total_abc);
        $this->assertSame('SVP', $rfq->procurementMode->code);
        $this->assertSame([$a->id, $b->id], $rfq->requests()->pluck('id')->all());

        // One mode per RFQ: the desktop (public bidding) stays off
        $this->refused(fn () => $this->rfqs->addLines($rfq, $this->bac, [$a->items[1]->id]), 'another procurement mode');
        $this->assertCount(0, $this->rfqs->availableRequests($rfq->fresh()));   // no SVP items left to quote
        $this->refused(fn () => $this->rfqs->issue($rfq->fresh(), $this->bac), 'deadline');

        $rfq = $this->rfqs->issue($rfq->fresh(), $this->bac, ['submission_deadline' => '2026-10-23 17:00', 'signatory_name' => 'Ana Santos', 'signatory_designation' => 'Head, BAC Secretariat']);
        $this->assertSame(RfqStatus::Issued, $rfq->status);
        $this->assertSame('2026-0001', $rfq->rfq_no);
        $this->refused(fn () => $this->rfqs->addLines($rfq, $this->bac, [$a->items[1]->id]), 'can no longer be changed');

        $second = $this->draftRfq();
        $this->rfqs->addLines($second, $this->bac, [$a->items[1]->id]);   // the desktop on its own (CB) RFQ
        $second = $this->rfqs->issue($second->fresh(), $this->bac, ['submission_deadline' => '2026-10-30 17:00']);
        $this->assertSame('2026-0002', $second->rfq_no);
    }

    public function test_a_line_is_on_one_open_rfq_and_cancelling_frees_it(): void
    {
        $pr = $this->submittedPr([[$this->hdd, 2]]);
        $first = $this->draftRfq();
        $this->rfqs->addLines($first, $this->bac, [$pr->items[0]->id]);

        $other = $this->draftRfq();
        $this->assertCount(0, $this->rfqs->availableRequests($other));
        $this->refused(fn () => $this->rfqs->addLines($other, $this->bac, [$pr->items[0]->id]), 'already on another RFQ');

        $first = $this->rfqs->issue($first->fresh(), $this->bac, ['submission_deadline' => '2026-10-23 17:00', 'signatory_name' => 'Ana Santos']);
        $this->rfqs->cancel($first, $this->bac, 'No quotations received');

        $this->assertCount(1, $this->rfqs->availableRequests($other));
        $this->rfqs->addLines($other, $this->bac, [$pr->items[0]->id]);
        $this->assertCount(1, $other->items()->get());
    }

    public function test_a_request_on_an_open_rfq_cannot_be_revised_or_cancelled(): void
    {
        $pr = $this->submittedPr([[$this->hdd, 2]]);
        $rfq = $this->draftRfq();
        $this->rfqs->addLines($rfq, $this->bac, [$pr->items[0]->id]);

        $this->refused(fn () => $this->requests->revise($pr, $this->staff), 'is in process at the BAC (RFQ (draft))');
        $this->refused(fn () => $this->requests->cancel($pr, $this->staff, 'x'), 'Ask the BAC to return it');

        // The office's page: no Revise / Cancel while in process
        $this->actingAs($this->staff)->get(route('procurement.requests.show', $pr))->assertOk()
            ->assertSee('In process at the BAC')->assertDontSee('id="btn_cancel"', false)->assertDontSee('id="btn_revise"', false);

        $this->rfqs->removeLine($rfq, $this->bac, $rfq->items()->first()->id);
        $this->assertNull($rfq->fresh()->procurement_mode_id);

        // Off the RFQ it is still locked: the BAC returns it (from the item picker) for revision
        $this->refused(fn () => $this->requests->revise($pr, $this->staff), 'submitted and locked');
        $this->actingAs($this->bac)->postJson(route('procurement.rfqs.return-to-office', $pr), ['return_for' => 'revision', 'reason' => 'Wrong unit'])->assertOk();
        $this->assertCount(0, $this->rfqs->availableRequests($rfq->fresh()));   // returned: off the list
        $revision = $this->requests->revise($pr->fresh(), $this->staff);

        // Being revised: it waits off the list
        $this->refused(fn () => $this->rfqs->addLines($rfq, $this->bac, [$pr->items[0]->id]), 'was returned to the office');
        $revision = $this->requests->submit($revision->fresh(), $this->staff);
        $this->assertSame([$revision->id], $this->rfqs->availableRequests($rfq->fresh())->pluck('id')->all());
    }

    public function test_the_bac_returns_a_request_in_process_for_revision_or_cancellation(): void
    {
        $a = $this->submittedPr([[$this->hdd, 2]]);
        $b = $this->submittedPr([[$this->ram, 4]]);
        $rfq = $this->rfqs->addLines($this->draftRfq(), $this->bac, [$a->items[0]->id, $b->items[0]->id]);
        $rfq = $this->rfqs->issue($rfq, $this->bac, ['submission_deadline' => '2026-10-23 17:00', 'signatory_name' => 'Ana Santos']);
        $aoqs = app(\App\Services\Procurement\AoqService::class);
        $aoq = $aoqs->start($rfq, $this->bac);
        $items = $rfq->items()->with('line')->get()->keyBy(fn ($i) => $i->line->purchase_request_id);
        $aoqs->saveSupplier($aoq, $this->bac, ['name' => 'Alpha Trading'], [$items[$a->id]->id => ['unit_price' => '4800'], $items[$b->id]->id => ['unit_price' => '2900']]);

        // For revision, over HTTP: A's items and quotation come off; the RFQ goes on with B
        $this->bac->givePermissionTo('manage rfq');
        $this->actingAs($this->bac)->postJson(route('procurement.rfqs.return-request', [$rfq, $a]), ['return_for' => 'revision'])
            ->assertStatus(422)->assertJsonPath('errors.reason.0', 'State why it is returned.');
        $this->postJson(route('procurement.rfqs.return-request', [$rfq, $a]), ['return_for' => 'revision', 'reason' => 'Specs unclear'])
            ->assertOk()->assertJsonPath('url', null);
        $a->refresh();
        $this->assertTrue($a->isReturned());
        $this->assertSame([$b->id], $rfq->fresh()->requests()->pluck('id')->all());
        $this->assertEquals('12000.00', $rfq->fresh()->total_abc);
        $this->assertSame(1, $aoq->quotes()->count());
        $this->assertSame('returned', $a->events()->get()->last()->event);
        $this->assertSame(1, $this->staff->notifications()->count());

        // Returned: on no RFQ until revised; the office revises it
        $this->refused(fn () => $this->rfqs->addLines($this->draftRfq(), $this->bac, [$a->items[0]->id]), 'returned to the office for revision');
        $this->actingAs($this->staff)->get(route('procurement.requests.show', $a))->assertSee('Returned by the BAC for revision')->assertSee('Specs unclear')->assertSee('id="btn_revise"', false);
        $revision = $this->requests->submit($this->requests->revise($a, $this->staff), $this->staff);
        $this->assertFalse($revision->fresh()->isReturned());
        $this->assertCount(1, $this->rfqs->availableRequests($this->draftRfq()));

        // For cancellation: the last request out, the RFQ is cancelled and its draft AOQ deleted; the office cancels it
        app(\App\Services\Procurement\RfqService::class)->returnRequest($rfq, $b, $this->bac, 'cancellation', 'Budget withdrawn');
        $this->assertSame(RfqStatus::Cancelled, $rfq->fresh()->status);
        $this->assertNull($aoq->fresh());
        $this->assertSame(\App\Enums\RequestStatus::Cancelled, $this->requests->cancel($b->fresh(), $this->staff, 'Budget withdrawn')->status);
    }

    public function test_a_request_cannot_be_returned_once_the_aoq_is_with_the_twg(): void
    {
        $pr = $this->submittedPr([[$this->hdd, 2]]);
        $rfq = $this->rfqs->issue($this->rfqs->addLines($this->draftRfq(), $this->bac, [$pr->items[0]->id]), $this->bac,
            ['submission_deadline' => '2026-10-23 17:00', 'signatory_name' => 'Ana Santos']);
        $aoqs = app(\App\Services\Procurement\AoqService::class);
        $aoq = $aoqs->start($rfq, $this->bac);
        $aoqs->saveSupplier($aoq, $this->bac, ['name' => 'Alpha Trading'], [$rfq->items()->first()->id => ['unit_price' => '4800']]);
        $twg = User::factory()->create(['is_activated' => 1]);
        $twg->givePermissionTo(\App\Models\Procurement\Aoq::EVALUATE_PERMISSION);
        $aoqs->sendToTwg($aoq, $this->bac, $twg);

        $this->refused(fn () => $this->rfqs->returnRequest($rfq, $pr, $this->bac, 'revision', 'x'), 'recall or reopen it first');
        $this->refused(fn () => $this->rfqs->returnRequest($rfq, $pr, $this->bac, 'later', 'x'), 'for revision or for cancellation');
    }

    public function test_only_submitted_requests_of_the_region_go_on_an_rfq(): void
    {
        $draft = $this->requests->create(RequestKind::Pr, $this->ppmp->paps()->first(), $this->staff, []);
        $line = $this->requests->saveLine($draft, ['ppmp_item_id' => $this->hdd->id, 'unit' => 'piece', 'description' => 'HDD', 'quantity' => 1, 'unit_cost' => '5000']);
        $rfq = $this->draftRfq();
        $this->refused(fn () => $this->rfqs->addLines($rfq, $this->bac, [$line->id]), 'only submitted requests');

        $pr = $this->submittedPr([[$this->ram, 1]]);
        $vis = $this->rfqs->create($this->bac, Region::Vis, ['title' => 'Visayas']);
        $this->assertCount(0, $this->rfqs->availableRequests($vis));
        $this->refused(fn () => $this->rfqs->addLines($vis, $this->bac, [$pr->items[0]->id]), 'this RFQ is for Visayas');
    }

    /** An approved APP whose line for the project has the given mode (the BAC's decision). */
    protected function approvedAppSetting(PpmpItem $project, string $mode): void
    {
        $app = \App\Models\Procurement\AnnualProcurementPlan::create(['fiscal_year' => 2027, 'region' => 'lm', 'status' => 'approved', 'approved_at' => now()]);
        $line = $app->items()->create([
            'project_title' => $project->description, 'end_user' => 'MIS', 'description' => $project->description,
            'procurement_mode_id' => ProcurementMode::where('code', $mode)->value('id'), 'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01',
            'fund_source_id' => $project->fund_source_id, 'estimated_budget' => $project->estimated_budget,
        ]);
        $line->ppmpItems()->attach($project->id, ['annual_procurement_plan_id' => $app->id]);
    }

    public function test_the_bacs_mode_in_the_approved_app_applies(): void
    {
        // The PPMP says public bidding for the desktop; the BAC set it to SVP in the approved APP
        $this->approvedAppSetting($this->desktop, 'SVP');
        $this->assertSame('SVP', $this->desktop->fresh()->effectiveMode()->code);
        $this->assertSame('CB', $this->desktop->fresh()->procurementMode->code);   // the PPMP itself is unchanged
        $this->assertNull($this->hdd->fresh()->appMode());                        // not in an approved APP: the PPMP's mode
        $this->assertSame('SVP', $this->hdd->fresh()->effectiveMode()->code);

        $pr = $this->submittedPr([[$this->hdd, 1], [$this->desktop, 1]]);
        $this->assertSame('REGULAR PROCUREMENT', $pr->fresh()->procurementLabel());   // no longer FOR BIDDING

        $rfq = $this->draftRfq();
        $this->rfqs->addLines($rfq, $this->bac, $pr->items->pluck('id')->all());   // one mode now
        $this->assertSame('SVP', $rfq->fresh()->procurementMode->code);

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $this->ppmp))->assertOk()->assertSee('Set by the BAC in the APP: Small Value Procurement');
    }

    public function test_the_title_is_optional(): void
    {
        $this->actingAs($this->bac)->postJson(route('procurement.rfqs.store'), [])->assertOk();
        $rfq = Rfq::sole();
        $this->assertNull($rfq->title);
        $this->assertSame('No items yet', $rfq->label());

        $pr = $this->submittedPr([[$this->hdd, 1], [$this->ram, 1]]);
        $this->rfqs->addLines($rfq, $this->bac, $pr->items->pluck('id')->all());
        $this->assertSame('Hard Drive (4 TB) and 1 more', $rfq->fresh()->label());
        $this->get(route('procurement.rfqs.index'))->assertSee('Hard Drive (4 TB) and 1 more');
    }

    public function test_pages_picker_print_and_access(): void
    {
        $pr = $this->submittedPr([[$this->hdd, 2], [$this->ram, 3]]);

        $this->actingAs($this->staff)->get(route('procurement.rfqs.index'))->assertForbidden();

        $this->actingAs($this->bac)->get(route('procurement.rfqs.index'))->assertOk()->assertSee('No RFQs yet');
        $url = $this->postJson(route('procurement.rfqs.store'), ['title' => 'ICT supplies Q1'])->assertOk()->json('url');
        $rfq = Rfq::sole();
        $this->assertSame(Region::Lm, $rfq->region);

        $this->get($url)->assertOk()->assertSee('ICT supplies Q1')->assertSee('No items yet');
        $this->get(route('procurement.rfqs.picker', $rfq))->assertOk()->assertSee($pr->title())->assertSee('Hard Drive (4 TB)')->assertSee('RAM 16 GB');

        $this->postJson(route('procurement.rfqs.lines.store', $rfq), ['lines' => $pr->items->pluck('id')->all()])->assertOk()->assertJson(['message' => '2 items added.']);
        $this->postJson(route('procurement.rfqs.header', $rfq), ['delivery_period' => 15, 'signatory_name' => 'Ana Santos', 'signatory_designation' => 'Head, BAC Secretariat', 'canvassed_by_name' => 'Juan Dela Cruz'])->assertOk();
        $this->postJson(route('procurement.rfqs.header', $rfq), ['price_validity' => 'thirty'])->assertStatus(422);
        $this->postJson(route('procurement.rfqs.issue', $rfq), [])->assertStatus(422)->assertJson(['message' => 'Set the deadline for submitting quotations before issuing.']);
        $this->postJson(route('procurement.rfqs.issue', $rfq), ['submission_deadline' => '2026-10-23T17:00'])->assertOk();

        $this->get(route('procurement.rfqs.show', $rfq))->assertOk()->assertSee('RFQ 2026-0001')->assertSee('19,000.00')->assertSee('Cancel RFQ');
        $html = $this->get(route('procurement.rfqs.print', [$rfq, 'copies' => 3]))->assertOk()
            ->assertSee('REQUEST FOR QUOTATION')->assertSee('Quotation No.')->assertSee('2026-0001')->assertSee($pr->request_no)
            ->assertSee('October 23, 2026')->assertSee('Ana Santos')->assertSee('Head, BAC Secretariat')
            ->assertSee('Delivery period shall be within 15 calendar days.')->assertSee('Price validity shall be for a period of ____ calendar days.')
            ->assertSee('Canvassed by:')->assertSee('Juan Dela Cruz')->assertSee('FM-AFD-PPS-015,Rev.02')->getContent();
        $this->assertSame(3, substr_count($html, 'FM-AFD-PPS-015,Rev.02'));

        // The PR / JR on the RFQ opens as printed, for RFQ staff without PR access too; others' requests do not
        $this->assertFalse($this->bac->canAccessPermission('manage requests'));
        $this->get(route('procurement.rfqs.request', [$rfq, $pr, 'embed' => 1]))->assertOk()->assertSee('PURCHASE REQUEST')->assertSee($pr->request_no)->assertDontSee('Print / Save as PDF</button>\n    <button', false);
        $other = $this->submittedPr([[$this->ram, 1]]);
        $this->get(route('procurement.rfqs.request', [$rfq, $other]))->assertForbidden();

        // The PR shows where it is being quoted
        $this->actingAs($this->staff)->get(route('procurement.requests.show', $pr))->assertOk()->assertSee('On RFQ 2026-0001');

        // Another region's RFQ stays out of sight
        $visBac = User::factory()->create(['is_activated' => 1, 'region' => 'vis']);
        $visBac->givePermissionTo('manage rfq');
        $this->actingAs($visBac)->get(route('procurement.rfqs.show', $rfq))->assertForbidden();
        $this->get(route('procurement.rfqs.index'))->assertOk()->assertDontSee('2026-0001');
    }
}
