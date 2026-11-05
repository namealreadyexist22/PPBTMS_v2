<?php

namespace Tests\Feature\Procurement;

use App\DataTables\Procurement\BiddingsDataTable;
use App\DataTables\Procurement\PrReceivingDataTable;
use App\Enums\BiddingStatus;
use App\Enums\BiddingStep;
use App\Enums\ProjectType;
use App\Enums\Region;
use App\Enums\RequestKind;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\Bidding;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\PpmpItem;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\PurchaseRequest;
use App\Models\Procurement\PurchaseRequestEvent;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\BiddingService;
use App\Services\Procurement\PpmpService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Procurement\RfqService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class BiddingTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\ReadsDataTables;

    protected PurchaseRequestService $requests;
    protected BiddingService $biddings;
    protected User $staff;
    protected User $bac;
    protected Ppmp $ppmp;
    protected PpmpItem $desktop;   // CB, COB
    protected PpmpItem $laptop;    // CB, COB
    protected PpmpItem $server;    // CB, SIDA (another department's PPMP)
    protected PpmpItem $hdd;       // SVP
    protected Ppmp $sidaPpmp;
    protected User $sidaStaff;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        Carbon::setTestNow('2026-11-04 09:00:00');

        $ppmps = app(PpmpService::class);
        $this->requests = app(PurchaseRequestService::class);
        $this->biddings = app(BiddingService::class);

        $head = User::factory()->create(['is_activated' => 1]);
        $department = Office::create(['type' => 'department', 'acronym' => 'PPSPD', 'name' => 'PLANNING DEPARTMENT', 'budget_fund' => 'regular']);
        $division = Office::create(['type' => 'division', 'code' => '05010', 'acronym' => 'PPPD', 'name' => 'Planning Division', 'parent_id' => $department->id, 'head_user_id' => $head->id]);
        $section = Office::create(['type' => 'section', 'code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS Section', 'parent_id' => $division->id]);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $section->id]);
        $this->staff->givePermissionTo(['manage requests', 'manage ppmp']);
        $head->givePermissionTo(['manage requests', 'manage ppmp']);
        $this->bac = User::factory()->create(['is_activated' => 1, 'fname' => 'Ana', 'lname' => 'Santos']);
        $this->bac->givePermissionTo([Bidding::MANAGE_PERMISSION, 'manage rfq']);

        $this->ppmp = $ppmps->create($section, 2027, $this->staff);
        $pap = $ppmps->addPap($this->ppmp, '27-05012-01', 'ICT Infrastructure');
        $this->desktop = $ppmps->addItem($this->ppmp, $this->project($pap->id, 'Desktop Computer', 10, '60000', 'CB', 'COB'));
        $this->laptop = $ppmps->addItem($this->ppmp, $this->project($pap->id, 'Laptop', 10, '80000', 'CB', 'COB'));
        $this->hdd = $ppmps->addItem($this->ppmp, $this->project($pap->id, 'Hard Drive (4 TB)', 8, '5000', 'SVP', 'COB'));
        $ppmps->submit($this->ppmp, $this->staff);
        $this->ppmp = $ppmps->approve($this->ppmp->fresh(), $head);

        // A SIDA-funded department plans the server (a department is budgeted under one fund)
        $sidaHead = User::factory()->create(['is_activated' => 1]);
        $sidaDept = Office::create(['type' => 'department', 'acronym' => 'SRED', 'name' => 'RESEARCH DEPARTMENT', 'budget_fund' => 'sida']);
        $sidaDivision = Office::create(['type' => 'division', 'code' => '06010', 'acronym' => 'RD', 'name' => 'Research Division', 'parent_id' => $sidaDept->id, 'head_user_id' => $sidaHead->id]);
        $sidaSection = Office::create(['type' => 'section', 'code' => '06011', 'acronym' => 'LAB', 'name' => 'Laboratory', 'parent_id' => $sidaDivision->id]);
        $this->sidaStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $sidaSection->id]);
        $this->sidaStaff->givePermissionTo(['manage requests', 'manage ppmp']);
        $sidaHead->givePermissionTo(['manage requests', 'manage ppmp']);
        $this->sidaPpmp = $ppmps->create($sidaSection, 2027, $this->sidaStaff);
        $sidaPap = $ppmps->addPap($this->sidaPpmp, '27-06011-01', 'Laboratory Systems');
        $this->server = $ppmps->addItem($this->sidaPpmp, $this->project($sidaPap->id, 'Server', 2, '900000', 'CB', 'SIDA'));
        $ppmps->submit($this->sidaPpmp, $this->sidaStaff);
        $this->sidaPpmp = $ppmps->approve($this->sidaPpmp->fresh(), $sidaHead);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    protected function project(int $papId, string $description, int $quantity, string $unitCost, string $mode, string $fund): array
    {
        return [
            'ppmp_pap_id' => $papId, 'description' => $description, 'project_type' => ProjectType::Goods, 'quantity' => $quantity,
            'unit_id' => Unit::first()->id, 'unit_cost' => $unitCost, 'quantity_size' => "{$quantity} units",
            'procurement_mode_id' => ProcurementMode::where('code', $mode)->value('id'), 'fund_source_id' => FundSource::where('code', $fund)->value('id'),
            'allotment_class' => 'co', 'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'delivery_period' => 'May 2027',
            'estimated_budget' => (string) ($quantity * (float) $unitCost),
        ];
    }

    /** A submitted PR with the given [project, quantity] lines (all of one PPMP: MIS's, or the SIDA one for the server), received by the BAC unless $receive is false. */
    protected function submittedPr(array $lines, bool $receive = true): PurchaseRequest
    {
        $sida = $lines[0][0]->ppmp_id === $this->sidaPpmp->id;
        $staff = $sida ? $this->sidaStaff : $this->staff;
        $pr = $this->requests->create(RequestKind::Pr, ($sida ? $this->sidaPpmp : $this->ppmp)->paps()->first(), $staff, [
            'purpose' => 'ICT equipment', 'requested_by_name' => 'Bren Tique', 'approved_by_name' => 'Pablo Azcona',
        ]);
        foreach ($lines as [$project, $qty]) {
            $this->requests->saveLine($pr, ['ppmp_item_id' => $project->id, 'unit' => 'unit', 'description' => $project->description, 'quantity' => $qty, 'unit_cost' => $project->unit_cost]);
        }
        $pr = $this->requests->submit($pr->fresh(), $staff);

        return $receive ? $this->requests->receive($pr, $this->bac, now()) : $pr;
    }

    /** A new bidding (numbered for the fund now). */
    protected function bidding(string $fund = 'COB'): Bidding
    {
        return $this->biddings->create($this->bac, Region::Lm, FundSource::where('code', $fund)->sole(), ['title' => 'ICT equipment']);
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

    protected function step(Bidding $bidding, BiddingStep $step, array $data = []): Bidding
    {
        return $this->biddings->recordStep($bidding->fresh(), $this->bac, $step, $data);
    }

    public function test_the_bac_receives_a_request_before_it_goes_on_an_rfq_or_a_bidding(): void
    {
        $this->bac->givePermissionTo('receive requests');
        $pr = $this->submittedPr([[$this->desktop, 5], [$this->hdd, 2]], receive: false);
        $bidding = $this->bidding();

        // Not received yet: offered nowhere
        $this->assertCount(0, $this->biddings->availableRequests($bidding));
        $this->refused(fn () => $this->biddings->addLines($bidding, $this->bac, [$pr->items[0]->id]), 'not been received by the BAC');
        $rfq = app(RfqService::class)->create($this->bac, Region::Lm, []);
        $this->assertCount(0, app(RfqService::class)->availableRequests($rfq));

        // PR Receiving: the list of requests to receive (for bidding, here)
        $this->actingAs($this->staff)->get(route('procurement.receiving.index'))->assertForbidden();
        $this->actingAs($this->bac)->get(route('procurement.receiving.index'))->assertOk()->assertSee('tblPrReceiving', false)->assertSee('To receive');
        $rows = fn (array $params = []) => $this->tableResponse('procurement.receiving.index', PrReceivingDataTable::class, $params);
        $row = $rows()->json('data.0');
        $this->assertSame($pr->title(), $row['request_no']);
        $this->assertStringContainsString('For bidding', $row['route_to']);
        $this->assertStringContainsString('js-receive', $row['action']);
        $this->assertSame(1, $rows()->json('pending'));
        $this->get(route('procurement.receiving.request', [$pr, 'embed' => 1]))->assertOk()->assertSee('PURCHASE REQUEST');

        // Received: on or after it was submitted, not in the future, once
        $this->postJson(route('procurement.receiving.receive', $pr), ['date' => '2026-11-05'])->assertStatus(422)->assertJsonPath('errors.date.0', 'The date received cannot be in the future.');
        $this->postJson(route('procurement.receiving.receive', $pr), ['date' => '2026-11-03'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'received on or after that'));
        $this->postJson(route('procurement.receiving.receive', $pr), ['date' => '2026-11-04', 'remarks' => 'Signed copy'])
            ->assertOk()->assertJsonPath('message', "{$pr->title()} received. Its items can now go on a bidding.");
        $this->postJson(route('procurement.receiving.receive', $pr), ['date' => '2026-11-04'])->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'already received'));
        $this->assertSame($this->bac->id, $pr->fresh()->received_by);
        $event = PurchaseRequestEvent::where('purchase_request_id', $pr->id)->where('event', 'received')->sole();
        $this->assertSame('Received by the BAC on Nov 04, 2026', $event->description);
        $this->assertSame('Signed copy', $event->remarks);

        // Now offered: its desktop to a bidding, its hard drive to an RFQ
        $this->assertCount(0, $rows()->json('data'));
        $this->assertSame([$pr->title()], collect($rows(['tab' => 'received'])->json('data'))->pluck('request_no')->all());
        $this->assertSame(['Desktop Computer'], $this->biddings->availableRequests($bidding)->flatMap->available->pluck('description')->all());
        $this->assertSame(['Hard Drive (4 TB)'], app(RfqService::class)->availableRequests($rfq)->flatMap->available->pluck('description')->all());

        // One with a problem is returned to the office from here
        $other = $this->submittedPr([[$this->laptop, 1]], receive: false);
        $this->postJson(route('procurement.receiving.return', $other), ['return_for' => 'revision', 'reason' => 'Not signed'])->assertOk();
        $this->assertTrue($other->fresh()->isReturned());
        $this->refused(fn () => $this->requests->receive($other->fresh(), $this->bac, now()), 'was returned to the office');

        // Another region's request is not received here
        $visBac = User::factory()->create(['is_activated' => 1, 'region' => 'vis']);
        $visBac->givePermissionTo('receive requests');
        $this->actingAs($visBac)->postJson(route('procurement.receiving.receive', $other), ['date' => '2026-11-04'])->assertForbidden();
        $this->assertCount(0, $this->tableRows('procurement.receiving.index', PrReceivingDataTable::class, ['tab' => 'received']));
    }

    public function test_competitive_bidding_items_go_into_lots_of_one_fund_source(): void
    {
        $a = $this->submittedPr([[$this->desktop, 5], [$this->hdd, 2]]);
        $b = $this->submittedPr([[$this->laptop, 4]]);
        $s = $this->submittedPr([[$this->server, 1]]);
        $bidding = $this->bidding();

        // Only the bidding's fund's Competitive Bidding items are offered (the hard drive is SVP: it goes on an RFQ)
        $available = $this->biddings->availableRequests($bidding);
        $this->assertSame([$a->id, $b->id], $available->pluck('id')->all());
        $this->assertSame(['Desktop Computer'], $available->first()->available->pluck('description')->all());
        $this->refused(fn () => $this->biddings->addLines($bidding, $this->bac, [$a->items[1]->id]), 'it goes on an RFQ');
        $this->refused(fn () => $this->biddings->addLines($bidding, $this->bac, [$s->items[0]->id]), 'this bidding is for COB items');

        // Lot 1: the desktop; Lot 2: the laptop
        $this->biddings->addLines($bidding, $this->bac, [$a->items[0]->id]);
        $this->biddings->addLines($bidding, $this->bac, [$b->items[0]->id]);
        $this->assertCount(0, $this->biddings->availableRequests($bidding->fresh()));
        $bidding->refresh()->load('lots.items.line');
        $this->assertSame([1, 2], $bidding->lots->pluck('lot_no')->all());
        $this->assertEquals('300000.00', $bidding->lots[0]->abc);
        $this->assertEquals('620000.00', $bidding->total_abc);
        $this->assertSame([$a->id, $b->id], $bidding->requests()->pluck('id')->all());

        // A line is in one bidding at a time; and it goes on no RFQ. The SIDA server goes to a SIDA bidding.
        $other = $this->bidding();
        $this->refused(fn () => $this->biddings->addLines($other, $this->bac, [$a->items[0]->id]), 'already in another bidding');
        $this->assertSame(['Server'], $this->biddings->availableRequests($this->bidding('SIDA'))->flatMap->available->pluck('description')->all());
        $rfq = app(RfqService::class)->create($this->bac, Region::Lm, []);
        $this->refused(fn () => app(RfqService::class)->addLines($rfq, $this->bac, [$a->items[0]->id]), 'it goes to a bidding');

        // Move the laptop into Lot 1, then Lot 2 (empty) can go
        $laptopItem = $bidding->lots[1]->items[0];
        $lot1 = $bidding->lots[0];
        $this->refused(fn () => $this->biddings->deleteLot($bidding, $this->bac, $bidding->lots[1]->id), 'has items');
        $this->biddings->moveLine($bidding, $this->bac, $laptopItem->id, $lot1->id);
        $this->biddings->deleteLot($bidding, $this->bac, $bidding->lots[1]->id);
        $this->assertEquals('620000.00', $lot1->fresh()->abc);
        $lot = $this->biddings->saveLot($bidding, $this->bac, null, 'Laptops');
        $this->assertSame('Lot 2 — Laptops', $lot->name());

        // The trail of each request says where its items went
        $this->assertSame('For public bidding: put in Lot 2 of Bidding 2026-COB-0001: Laptop', PurchaseRequestEvent::where('purchase_request_id', $b->id)->where('event', 'bid_added')->value('description'));

        // The bidding's request cannot be returned to the office while its items are in it
        $this->refused(fn () => $this->requests->returnToOffice($b->fresh(), $this->bac, 'revision', 'x'), 'take them off the bidding first');
        $this->biddings->removeLine($bidding, $this->bac, $laptopItem->id);
        $this->requests->returnToOffice($b->fresh(), $this->bac, 'revision', 'Wrong specs');
        $this->assertNotNull($b->fresh()->returned_at);
    }

    public function test_steps_follow_the_process_and_numbering_is_per_year_and_fund(): void
    {
        $a = $this->submittedPr([[$this->desktop, 5]]);

        // Numbered when created: YYYY-FUND-NNNN, series per year and fund
        $bidding = $this->bidding();
        $this->assertSame('2026-COB-0001', $bidding->bid_no);
        $this->assertSame(BiddingStatus::Ongoing, $bidding->status);
        $this->assertSame('2026-SIDA-0001', $this->bidding('SIDA')->bid_no);
        $this->assertSame('2026-COB-0002', $this->bidding()->bid_no);

        // A step needs the lots with their items (each step goes on the PRs' trail)
        $this->refused(fn () => $this->step($bidding, BiddingStep::PreprocSet, ['preproc_at' => '2026-11-10 09:00']), 'Add the items to bid first');
        $this->biddings->addLines($bidding, $this->bac, [$a->items[0]->id]);
        $this->biddings->saveLot($bidding, $this->bac, null, null);   // an empty Lot 2
        $this->refused(fn () => $this->step($bidding, BiddingStep::Advertised, ['date' => '2026-11-04', 'bidding_at' => '2026-11-25 09:00']), 'Lot 2 has no items');
        $this->biddings->deleteLot($bidding, $this->bac, $bidding->lots()->where('lot_no', 2)->value('id'));
        $this->refused(fn () => $this->step($bidding, BiddingStep::Bidding, ['date' => '2026-11-04']), 'Next is: Set for Pre-Procurement Conference or Advertisement posted');

        // Pre-procurement conference: set, re-set, held
        $this->step($bidding, BiddingStep::PreprocSet, ['preproc_at' => '2026-11-10 09:00']);
        $this->step($bidding, BiddingStep::PreprocSet, ['preproc_at' => '2026-11-11 13:30', 'remarks' => 'Chair on leave']);
        $this->assertSame('2026-11-11 13:30', $bidding->fresh()->preproc_at->format('Y-m-d H:i'));
        $this->refused(fn () => $this->step($bidding, BiddingStep::Bidding, ['date' => '2026-11-11']), 'Next is');
        $this->step($bidding, BiddingStep::PreprocHeld, ['date' => '2026-11-11']);

        // Advertised: the dates and links; the bidding is after the pre-bid
        $this->refused(fn () => $this->step($bidding, BiddingStep::Advertised, ['date' => '2026-11-12', 'bidding_at' => '2026-11-20 09:00', 'prebid_at' => '2026-11-25 09:00']), 'after the pre-bid');
        $bidding = $this->step($bidding, BiddingStep::Advertised, [
            'date' => '2026-11-12', 'bidding_at' => '2026-12-03 09:00', 'prebid_at' => '2026-11-20 09:00',
            'philgeps_link' => 'https://notices.philgeps.gov.ph/12345', 'website_link' => 'https://sra.gov.ph/bids/1',
        ]);
        $this->assertSame('https://notices.philgeps.gov.ph/12345', $bidding->philgeps_link);
        $this->assertFalse($bidding->lotsEditable());
        $this->refused(fn () => $this->biddings->saveLot($bidding, $this->bac, null, 'More'), 'is advertised');

        // Pre-bid held, bidding re-set (with the reason); a supplemental bulletin re-sets it again
        $this->refused(fn () => $this->step($bidding, BiddingStep::PrebidHeld, ['date' => '2026-11-20', 'bidding_at' => '2026-12-05 09:00']), 'State why');
        $this->step($bidding, BiddingStep::PrebidHeld, ['date' => '2026-11-20', 'bidding_at' => '2026-12-05 09:00', 'remarks' => 'Bidders asked for more time']);
        $this->step($bidding, BiddingStep::Supplemental, ['date' => '2026-11-25', 'bidding_at' => '2026-12-10 09:00', 'remarks' => 'Specs of item 1 amended']);
        $bidding->refresh();
        $this->assertSame('2026-12-10 09:00', $bidding->bidding_at->format('Y-m-d H:i'));
        $this->assertSame(2, $bidding->bidding_resets);
        $this->assertSame('2026-12-05 09:00:00', $bidding->events()->get()->last()->details['previous']);

        // Public bidding held; the bids' evaluation comes next
        $this->step($bidding, BiddingStep::Bidding, ['date' => '2026-12-10']);
        $this->assertSame([], $this->biddings->nextSteps($bidding->fresh()));
        $this->refused(fn () => $this->step($bidding, BiddingStep::Supplemental, ['date' => '2026-12-11']), 'evaluated next');

        // Every step is on the request's trail, after its receiving
        $trail = PurchaseRequestEvent::where('purchase_request_id', $a->id)->orderBy('id')->pluck('description');
        $this->assertSame('Received by the BAC on Nov 04, 2026', $trail[2]);
        $this->assertTrue($trail->contains('Pre-procurement conference of Bidding 2026-COB-0001 held Nov 11, 2026'));
        $this->assertTrue($trail->contains('Pre-bid conference held Nov 20, 2026; public bidding re-set to Dec 05, 2026 9:00 AM'));
        $this->assertTrue($trail->contains('Public bidding held Dec 10, 2026'));
        $this->assertSame(7, $bidding->events()->count());
    }

    public function test_cancelling_frees_the_items_and_the_number_is_not_reused(): void
    {
        $a = $this->submittedPr([[$this->desktop, 5]]);
        $bidding = $this->bidding();
        $this->biddings->addLines($bidding, $this->bac, [$a->items[0]->id]);
        $this->biddings->cancel($bidding, $this->bac, 'Requirements changed');
        $this->assertSame(BiddingStatus::Cancelled, $bidding->fresh()->status);
        $this->assertSame([], $this->biddings->nextSteps($bidding->fresh()));
        $this->refused(fn () => $this->biddings->cancel($bidding->fresh(), $this->bac, 'x'), 'already cancelled');
        $this->refused(fn () => $this->biddings->saveLot($bidding->fresh(), $this->bac, null, 'x'), 'is cancelled');

        // Free again; the next bidding of the fund takes the next number
        $again = $this->bidding();
        $this->assertSame('2026-COB-0002', $again->bid_no);
        $this->biddings->addLines($again, $this->bac, [$a->items[0]->id]);
        $this->assertSame([$a->id], $again->fresh()->requests()->pluck('id')->all());
    }

    public function test_the_pages_work_and_need_the_permission(): void
    {
        $a = $this->submittedPr([[$this->desktop, 5]]);
        $this->actingAs($this->staff)->get(route('procurement.biddings.index'))->assertForbidden();

        $this->actingAs($this->bac)->get(route('procurement.biddings.index'))->assertOk()->assertSee('tblBiddings', false)->assertSee('New Bidding')->assertSee('Corporate Operating Budget');
        $this->postJson(route('procurement.biddings.store'), ['title' => 'ICT equipment 2027'])->assertStatus(422)->assertJsonPath('errors.fund_source_id.0', 'Choose the fund source; the bidding is numbered for it.');
        $url = $this->postJson(route('procurement.biddings.store'), ['title' => 'ICT equipment 2027', 'fund_source_id' => FundSource::where('code', 'COB')->value('id')])
            ->assertOk()->assertJsonPath('message', 'Bidding 2026-COB-0001 created. Add the items to bid, in lots.')->json('url');
        $bidding = Bidding::sole();

        $this->get($url)->assertOk()->assertSee('Bidding 2026-COB-0001')->assertSee('No items yet.')->assertSee('Set for Pre-Procurement Conference')->assertDontSee('Received by the BAC');
        $this->get(route('procurement.biddings.picker', $bidding))->assertOk()->assertSee($a->title())->assertSee('Desktop Computer');
        $this->postJson(route('procurement.biddings.lines.store', $bidding), ['lines' => [$a->items[0]->id]])->assertOk()->assertJsonPath('message', '1 item added.');
        $this->postJson(route('procurement.biddings.lots.store', $bidding), ['id' => $bidding->lots()->value('id'), 'title' => 'Desktops'])->assertOk();

        // Steps: only the next one opens; its form posts the dates
        $this->get(route('procurement.biddings.steps.entry', [$bidding, 'bidding']))->assertForbidden();
        $this->get(route('procurement.biddings.steps.entry', [$bidding, 'received']))->assertNotFound();
        $this->get(route('procurement.biddings.steps.entry', [$bidding, 'advertised']))->assertOk()->assertSee('BID_STEP_MODAL', false)->assertSee('can no longer change');
        $this->postJson(route('procurement.biddings.steps.store', [$bidding, 'advertised']), ['bidding_at' => '2026-11-25 09:00'])->assertStatus(422)->assertJsonPath('errors.date.0', 'Set the date.');
        $this->postJson(route('procurement.biddings.steps.store', [$bidding, 'advertised']), ['date' => '2026-11-05', 'bidding_at' => '2026-11-25 09:00', 'philgeps_link' => 'not a link'])
            ->assertStatus(422)->assertJsonValidationErrors('philgeps_link');
        $this->postJson(route('procurement.biddings.steps.store', [$bidding, 'advertised']), ['date' => '2026-11-05', 'bidding_at' => '2026-11-25 09:00'])
            ->assertOk()->assertJsonPath('message', 'Advertisement posted: recorded.');

        // The page: the next steps to record and the dates; the full record in the History modal
        $this->get($url)->assertOk()->assertSee('2026-COB-0001')->assertSee('Lot 1 — Desktops')->assertSee('Nov 25, 2026 9:00 AM')->assertSee('Fixed since the advertisement')
            ->assertSee('Pre-bid Conference held')->assertSee('Supplemental bid bulletin')->assertSee('BID_HISTORY_MODAL', false)->assertDontSee('15. Delivered');

        // The bidders' document checklist is a Procurement Lookup
        $this->assertSame(10, \App\Models\Procurement\BidDocument::active()->count());
        $lookups = User::factory()->create(['is_activated' => 1]);
        $lookups->givePermissionTo('manage lookups');
        $this->actingAs($lookups)->get(route('core.lookups.index', ['type' => 'bid-documents']))->assertOk()->assertSee('Omnibus Sworn Statement');
        $this->actingAs($this->bac);
        $row = $this->tableRows('procurement.biddings.index', BiddingsDataTable::class)->first();
        $this->assertSame('2026-COB-0001', $row['bid_no']);
        $this->assertStringContainsString('Advertisement posted', $row['stage_text']);
        $this->assertSame($url, $row['DT_RowAttr']['data-url']);
        $this->assertCount(1, $this->tableRows('procurement.biddings.index', BiddingsDataTable::class, ['q' => $a->request_no]));
        $this->assertCount(0, $this->tableRows('procurement.biddings.index', BiddingsDataTable::class, ['year' => 2025]));

        // Another region's bidding stays out of sight
        $visBac = User::factory()->create(['is_activated' => 1, 'region' => 'vis']);
        $visBac->givePermissionTo(Bidding::MANAGE_PERMISSION);
        $this->actingAs($visBac)->get($url)->assertForbidden();
        $this->assertCount(0, $this->tableRows('procurement.biddings.index', BiddingsDataTable::class, ['year' => 'all']));
    }
}
