<?php

namespace Tests\Feature\Procurement;

use App\Enums\AoqStatus;
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
use App\Models\Procurement\Aoq;
use App\Models\Procurement\Rfq;
use App\Models\Procurement\Supplier;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use App\Services\Procurement\PurchaseRequestService;
use App\Services\Procurement\AoqService;
use App\Services\Procurement\OfficialsService;
use App\Services\Procurement\RfqService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class AoqTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected PurchaseRequestService $requests;
    protected RfqService $rfqs;
    protected AoqService $aoqs;
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
        Storage::fake('local');
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        Carbon::setTestNow('2026-10-16 09:00:00');

        $this->ppmps = app(PpmpService::class);
        $this->requests = app(PurchaseRequestService::class);
        $this->rfqs = app(RfqService::class);
        $this->aoqs = app(AoqService::class);

        $head = User::factory()->create(['is_activated' => 1]);
        $department = Office::create(['type' => 'department', 'acronym' => 'PPSPD', 'name' => 'PLANNING DEPARTMENT', 'budget_fund' => 'regular']);
        $division = Office::create(['type' => 'division', 'code' => '05010', 'acronym' => 'PPPD', 'name' => 'Planning Division', 'parent_id' => $department->id, 'head_user_id' => $head->id]);
        $section = Office::create(['type' => 'section', 'code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS Section', 'parent_id' => $division->id]);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $section->id]);
        $this->staff->givePermissionTo(['manage requests', 'manage ppmp']);
        $head->givePermissionTo(['manage requests', 'manage ppmp']);
        $this->bac = User::factory()->create(['is_activated' => 1, 'fname' => 'Ana', 'lname' => 'Santos', 'designation' => 'Head, BAC Secretariat']);
        $this->bac->givePermissionTo(['manage rfq', 'manage aoq']);

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

    /** An issued RFQ with 2 HDD (ABC 10,000) and 4 RAM (ABC 12,000). */
    protected function issuedRfq(): Rfq
    {
        $pr = $this->submittedPr([[$this->hdd, 2], [$this->ram, 4]]);
        $rfq = $this->rfqs->addLines($this->draftRfq(), $this->bac, $pr->items->pluck('id')->all());

        return $this->rfqs->issue($rfq, $this->bac, ['submission_deadline' => '2026-10-20 10:00', 'signatory_name' => 'Ana Santos']);
    }

    /** [rfq item id of HDD, of RAM] */
    protected function itemIds(Rfq $rfq): array
    {
        return $rfq->items()->with('line')->get()->sortBy(fn ($i) => $i->line->description === 'RAM 16 GB')->pluck('id')->all();
    }

    protected function quote(Aoq $aoq, string $name, array $prices, array $extra = [], array $quoteExtra = []): void
    {
        $quotes = collect($prices)->mapWithKeys(fn ($price, $itemId) => [$itemId => ['unit_price' => $price] + ($quoteExtra[$itemId] ?? [])])->all();
        $this->aoqs->saveSupplier($aoq, $this->bac, ['name' => $name] + $extra, $quotes);
    }

    /** A TWG member (evaluate permission). */
    protected function twgMember(string $fname = 'Jay', string $lname = 'Dizon'): User
    {
        $user = User::factory()->create(['is_activated' => 1, 'fname' => $fname, 'minitial' => null, 'lname' => $lname, 'designation' => 'Engineer III']);
        $user->givePermissionTo(Aoq::EVALUATE_PERMISSION);

        return $user;
    }

    /** Send to a TWG member who finds every offer meets the specs except $failing ([supplier name, rfq item id]). */
    protected function evaluated(Aoq $aoq, array $failing = []): Aoq
    {
        $twg = $this->twgMember();
        $this->aoqs->sendToTwg($aoq, $this->bac, $twg);
        $findings = $aoq->quotes()->with('supplier')->get()->mapWithKeys(fn ($q) => [$q->id => [
            'is_compliant' => in_array([$q->supplier->name, $q->rfq_item_id], $failing, true) ? '0' : '1',
        ]])->all();

        return $this->aoqs->completeEvaluation($aoq, $twg, $findings, 'Checked.');
    }

    public function test_each_item_goes_to_its_lowest_responsive_quote_within_the_abc(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->assertSame($aoq->id, $this->aoqs->start($rfq, $this->bac)->id);   // one per RFQ

        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800', $ram => '2900']);
        $this->quote($aoq, 'Beta Supply', [$hdd => '4500', $ram => '3100']);       // RAM over ABC (12,400 > 12,000)
        $this->quote($aoq, 'Gamma Corp', [$hdd => '4000', $ram => '2000'], ['is_responsive' => false, 'remarks' => 'No PhilGEPS']);
        $this->quote($aoq, 'Delta Inc', [$hdd => '4100', $ram => '2500']);
        $this->refused(fn () => $this->aoqs->award($aoq, $this->bac), 'the TWG evaluates the offers before the award');

        $aoq = $this->evaluated($aoq, [['Delta Inc', $ram]]);   // Delta's RAM does not meet the specs
        $this->assertSame(AoqStatus::Evaluated, $aoq->status);
        $this->assertSame('Jay Dizon', $aoq->evaluated_by_name);

        $rows = $this->aoqs->evaluate($aoq->fresh())->keyBy(fn ($r) => $r['item']->id);
        $ids = $aoq->suppliers()->pluck('id', 'name');
        $this->assertSame([$ids['Delta Inc']], $rows[$hdd]['lowest']);
        $this->assertSame('Over ABC', $rows[$ram]['quotes'][$ids['Beta Supply']]['issue']);
        $this->assertSame('Not responsive', $rows[$ram]['quotes'][$ids['Gamma Corp']]['issue']);
        $this->assertSame('Not compliant', $rows[$ram]['quotes'][$ids['Delta Inc']]['issue']);
        $this->assertSame([$ids['Alpha Trading']], $rows[$ram]['lowest']);
        $this->assertSame(1160000, $rows[$ram]['quotes'][$ids['Alpha Trading']]['total_cents']);

        $aoq = $this->aoqs->award($aoq, $this->bac);
        $this->assertSame(AoqStatus::Awarded, $aoq->status);
        $rows = $this->aoqs->evaluate($aoq->fresh())->keyBy(fn ($r) => $r['item']->id);
        $this->assertSame($ids['Delta Inc'], $rows[$hdd]['awarded']);
        $this->assertSame($ids['Alpha Trading'], $rows[$ram]['awarded']);

        // Locked once awarded; reopening clears the award
        $this->refused(fn () => $this->quote($aoq, 'Late Bidder', [$hdd => '1']), 'reopen it');
        $this->aoqs->reopen($aoq, $this->bac);
        $this->assertSame(0, $aoq->quotes()->where('is_awarded', true)->count());
    }

    public function test_per_lot_all_items_go_to_the_lowest_complete_lot_that_meets_the_specs(): void
    {
        $pr = $this->submittedPr([[$this->hdd, 2], [$this->ram, 4]]);   // ABC 10,000 + 12,000 = 22,000
        $rfq = $this->rfqs->addLines($this->draftRfq(), $this->bac, $pr->items->pluck('id')->all());
        $rfq = $this->rfqs->issue($rfq, $this->bac, ['submission_deadline' => '2026-10-20 10:00', 'signatory_name' => 'Ana Santos', 'award_basis' => 'lot']);
        $this->assertTrue($rfq->fresh()->isPerLot());
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);

        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800', $ram => '2900']);   // 9,600 + 11,600 = 21,200
        $this->quote($aoq, 'Beta Supply', [$hdd => '4000', $ram => '2500']);     // 18,000, but its RAM fails the specs
        $this->quote($aoq, 'Gamma Corp', [$hdd => '3000']);                      // RAM not quoted
        $this->quote($aoq, 'Delta Inc', [$hdd => '4400', $ram => '3100']);       // 8,800 + 12,400 = 21,200: RAM over its own ABC, the lot within
        $aoq = $this->evaluated($aoq, [['Beta Supply', $ram]]);

        $lot = $this->aoqs->lotStanding($aoq->fresh());
        $ids = $aoq->suppliers()->pluck('id', 'name');
        $this->assertSame(2200000, $lot['abc_cents']);
        $this->assertSame('Not compliant', $lot['suppliers'][$ids['Beta Supply']]['issue']);
        $this->assertSame('Incomplete lot', $lot['suppliers'][$ids['Gamma Corp']]['issue']);
        $this->assertNull($lot['suppliers'][$ids['Delta Inc']]['issue']);
        $this->assertEqualsCanonicalizing([$ids['Alpha Trading'], $ids['Delta Inc']], $lot['lowest']);

        // A tie: one pick for the whole lot
        $this->refused(fn () => $this->aoqs->award($aoq, $this->bac), 'The lot has a tie');
        $this->aoqs->award($aoq, $this->bac, ['lot' => $ids['Delta Inc']]);
        $rows = $this->aoqs->evaluate($aoq->fresh());
        $this->assertSame([$ids['Delta Inc'], $ids['Delta Inc']], $rows->pluck('awarded')->all());

        $this->actingAs($this->bac)->get(route('procurement.aoqs.show', $aoq))->assertOk()->assertSee('Award per lot (all items)')->assertSee('Lot rank 1')->assertSee('Incomplete lot');
        $this->get(route('procurement.aoqs.print', $aoq))->assertOk()->assertSee('TOTAL (1 LOT)')->assertSee('21,200.00');
        $this->get(route('procurement.rfqs.print', $rfq))->assertOk()->assertSee('awarded per lot');

        $admin = $this->twgMember('Super', 'Admin');
        $admin->assignRole('Super Admin');
        $this->actingAs($admin)->get(route('procurement.twg.show', $aoq))->assertOk()->assertSee('Who wins: awarded per lot')
            ->assertSee('js-lot-outcome', false)->assertSee('Incomplete lot: not every item quoted')->assertDontSee('<div class="js-outcome', false)
            // Each item in the lot's order (Beta 18,000, Alpha / Delta 21,200, Gamma incomplete), with the lot rank
            ->assertSeeInOrder(['Hard Drive (4 TB)', 'Beta Supply', 'Alpha Trading', 'Delta Inc', 'Gamma Corp', 'RAM 16 GB', 'Beta Supply', 'Alpha Trading', 'Delta Inc', 'Gamma Corp'])
            ->assertSee('>Lot 1<', false);
    }

    public function test_a_tie_is_picked_by_the_bac_and_an_item_without_a_counting_quote_fails(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->quote($aoq, 'Alpha Trading', [$hdd => '4500', $ram => '9000']);
        $this->quote($aoq, 'Beta Supply', [$hdd => '4500']);
        $aoq = $this->evaluated($aoq);

        $this->refused(fn () => $this->aoqs->award($aoq, $this->bac), 'has a tie');

        $beta = $aoq->suppliers()->where('name', 'Beta Supply')->value('id');
        $this->aoqs->award($aoq, $this->bac, [$hdd => $beta]);
        $rows = $this->aoqs->evaluate($aoq->fresh())->keyBy(fn ($r) => $r['item']->id);
        $this->assertSame($beta, $rows[$hdd]['awarded']);
        $this->assertNull($rows[$ram]['awarded']);
        $this->assertSame([], $rows[$ram]['lowest']);
    }

    public function test_suppliers_go_into_the_directory_and_cannot_be_deleted_while_on_an_abstract(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        Supplier::create(['name' => 'Beta Supply', 'address' => 'Quezon City', 'tin' => '111']);

        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800'], ['address' => 'Manila']);
        $this->quote($aoq, 'beta supply', [$hdd => '4700'], ['address' => 'Makati', 'contact' => '0917']);

        $alpha = Supplier::firstWhere('name', 'Alpha Trading');
        $this->assertSame('Manila', $alpha->address);
        $beta = Supplier::firstWhere('name', 'Beta Supply');
        $this->assertSame(['Quezon City', '0917'], [$beta->address, $beta->contact]);   // kept; only missing details filled
        $this->assertEqualsCanonicalizing([$alpha->id, $beta->id], $aoq->suppliers()->pluck('supplier_id')->all());

        $this->bac->givePermissionTo(Supplier::MANAGE_PERMISSION);
        $this->actingAs($this->bac)->get(route('procurement.aoqs.suppliers.entry', $aoq))->assertOk()->assertSee('data-address="Quezon City"', false);
        $this->deleteJson(route('procurement.suppliers.destroy'), ['id' => $alpha->id])->assertStatus(422)->assertJsonPath('message', '"Alpha Trading" is on 1 abstract(s). Deactivate it instead.');
    }

    public function test_starting_takes_the_bac_from_the_officials_and_needs_an_issued_rfq(): void
    {
        [$chair, $m1] = User::factory()->count(2)->create(['is_activated' => 1]);
        app(OfficialsService::class)->set(Region::Lm, ['bac_chair' => $chair->id], [$m1->id]);

        $this->refused(fn () => $this->aoqs->start($this->draftRfq(), $this->bac), 'only an issued RFQ');

        $rfq = $this->issuedRfq();
        $aoq = $this->aoqs->start($rfq, $this->bac);
        // Not stored: printed from Signatories & BAC unless changed on the AOQ
        $this->assertNull($aoq->bac_chair_name);
        $signatories = $this->aoqs->signatories($aoq);
        $this->assertSame($chair->fresh()->fullname, $signatories['bac_chair']);
        $this->assertSame([$m1->fresh()->fullname], $signatories['bac_members']);
        $this->assertSame([$this->bac->fresh()->fullname, 'Head, BAC Secretariat'], $signatories['prepared_by']);

        // Certified by the RFQ's signatory: typed in capitals without a designation, it prints the user's
        $signer = User::factory()->create(['is_activated' => 1, 'name_title' => null, 'fname' => 'Narciso', 'minitial' => 'R', 'lname' => 'Cabalquinto', 'name_suffix' => null, 'designation' => 'Supply Officer IV']);
        $rfq->update(['signatory_name' => 'NARCISO R. CABALQUINTO', 'signatory_designation' => null]);
        $this->assertSame(['NARCISO R. CABALQUINTO', 'Supply Officer IV'], $this->aoqs->signatories($aoq->fresh())['certified_by']);
        $this->assertNotNull($signer);
        $this->aoqs->updateHeader($aoq, $this->bac, ['bac_chair_name' => 'Juan Cruz', 'prepared_by_name' => '  ']);
        $this->assertSame('Juan Cruz', $this->aoqs->signatories($aoq->fresh())['bac_chair']);
        $this->assertSame($this->bac->fresh()->fullname, $this->aoqs->signatories($aoq->fresh())['prepared_by'][0]);

        // The RFQ cannot be cancelled while it has an abstract
        $this->refused(fn () => $this->rfqs->cancel($rfq->fresh(), $this->bac, 'x'), 'delete it first');
        $this->aoqs->deleteDraft($aoq);
        $this->rfqs->cancel($rfq->fresh(), $this->bac, 'x');
        $this->assertSame(RfqStatus::Cancelled, $rfq->fresh()->status);
    }

    public function test_the_pages_work_and_need_the_aoq_permission(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);

        $this->actingAs($this->bac)->get(route('procurement.aoqs.index'))->assertOk()->assertSee($rfq->rfq_no)->assertSee('Start AOQ');
        $url = $this->postJson(route('procurement.aoqs.start', $rfq))->assertOk()->json('url');
        $aoq = $rfq->aoq()->first();

        $this->get(route('procurement.aoqs.suppliers.entry', $aoq))->assertOk()->assertSee('Add Supplier');
        $this->post(route('procurement.aoqs.suppliers.store', $aoq), [
            'name' => 'Alpha Trading', 'is_responsive' => 1,
            'attachment' => UploadedFile::fake()->create('quotation.pdf', 50, 'application/pdf'),
            'quotes' => [
                $hdd => ['unit_price' => '4800', 'brand' => 'WD Red', 'file' => UploadedFile::fake()->create('wd-red.pdf', 50, 'application/pdf')],
                $ram => ['unit_price' => '', 'brand' => ''],
            ],
        ], ['Accept' => 'application/json'])->assertOk();
        $this->post(route('procurement.aoqs.suppliers.store', $aoq), ['name' => 'Beta', 'is_responsive' => 1, 'attachment' => UploadedFile::fake()->create('q.docx', 50)], ['Accept' => 'application/json'])
            ->assertStatus(422)->assertJsonPath('errors.attachment.0', 'Attach the quotation as a PDF.');
        $this->postJson(route('procurement.aoqs.suppliers.store', $aoq), ['name' => 'alpha trading', 'is_responsive' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'alpha trading is already on this abstract.');

        $alpha = $aoq->suppliers()->first();
        $hddQuote = $alpha->quotes()->where('rfq_item_id', $hdd)->first();
        Storage::disk('local')->assertExists([$alpha->attachment_path, $hddQuote->attachment_path]);
        $this->get(route('procurement.aoqs.attachment', [$aoq, 'quote', $hddQuote->id]))->assertOk()->assertHeader('Content-Type', 'application/pdf');

        $this->get($url)->assertOk()->assertSee('Alpha Trading')->assertSee('9,600.00')->assertSee('No quote')->assertSee('fewer than 3')->assertSee('Not evaluated')
            ->assertDontSee('lowest, on award')->assertDontSee('Rank 1');   // no ranking for the BAC before the TWG's evaluation
        $this->postJson(route('procurement.aoqs.header', $aoq), ['bac_chair_name' => 'Juan Cruz', 'bac_members' => ['Ana Reyes', '', null]])->assertOk();
        $this->assertSame(['Ana Reyes'], $aoq->fresh()->bac_members);

        // To the TWG member the BAC assigns; another TWG member cannot evaluate it
        $twg = $this->twgMember();
        $stranger = $this->twgMember('Other', 'Member');
        $this->postJson(route('procurement.aoqs.twg', $aoq))->assertStatus(422)->assertJsonPath('message', 'Choose the TWG evaluator under "Evaluated by" first.');
        $this->postJson(route('procurement.aoqs.header', $aoq), ['evaluator_id' => $stranger->id])->assertOk();
        $this->postJson(route('procurement.aoqs.header', $aoq), ['evaluator_id' => $twg->id])->assertOk();   // one evaluator: replaces
        $this->assertSame([$twg->id], $aoq->evaluators()->pluck('users.id')->all());
        $this->postJson(route('procurement.aoqs.header', $aoq), ['evaluator_id' => $this->bac->id])->assertStatus(422);   // not TWG
        $this->postJson(route('procurement.aoqs.twg', $aoq))->assertOk()->assertJsonPath('message', 'AOQ 2026-0001 sent to Jay Dizon for TWG evaluation.');
        $this->assertSame(1, $twg->notifications()->count());
        $this->postJson(route('procurement.aoqs.award', $aoq))->assertStatus(422);

        $this->get(route('procurement.aoqs.rfq', $aoq))->assertOk()->assertSee($rfq->title())->assertSee('Quotations due')->assertSee('Hard Drive (4 TB)')->assertSee('Open RFQ page');
        $this->actingAs($stranger)->get(route('procurement.aoqs.rfq', $aoq))->assertForbidden();
        $this->actingAs($twg)->get(route('procurement.aoqs.rfq', $aoq))->assertOk()->assertDontSee('Open RFQ page');   // the evaluator: details only
        $this->actingAs($stranger)->get(route('procurement.twg.show', $aoq))->assertForbidden();
        $this->get(route('procurement.aoqs.attachment', [$aoq, 'quote', $hddQuote->id]))->assertForbidden();

        $this->actingAs($twg)->get(route('procurement.twg.index'))->assertOk()->assertSee($aoq->title())->assertSee('Evaluate');
        $this->get(route('procurement.twg.show', $aoq))->assertOk()->assertSee('Complete Evaluation')->assertSee('Spec sheet')->assertSee('Quotation / proposal')->assertSee('1 · lowest')
            ->assertSee('Who wins an item')->assertSee('js-outcome', false)->assertSee('data-eligible="1"', false);
        $this->get(route('procurement.aoqs.attachment', [$aoq, 'supplier', $alpha->id]))->assertOk();
        $finding = ['findings' => [$hddQuote->id => ['is_compliant' => '1', 'twg_remarks' => 'WD Red 4 TB, NAS grade']], 'technical_comment' => 'Meets the specifications'];
        $this->postJson(route('procurement.twg.complete', $aoq), $finding)->assertOk();
        $this->assertSame(AoqStatus::Evaluated, $aoq->fresh()->status);

        $this->actingAs($this->bac)->get($url)->assertSee('Evaluated by the TWG')->assertSee('WD Red 4 TB, NAS grade');
        $this->postJson(route('procurement.aoqs.award', $aoq))->assertOk();
        $this->get(route('procurement.aoqs.print', $aoq))->assertOk()->assertSee('ABSTRACT OF QUOTATION')->assertSee('Juan Cruz')
            ->assertSee('Invited but no quote')->assertSee('Meets the specifications')->assertSee('Jay Dizon')->assertSee('ENGINEER III')->assertSee('Failed');
        $this->get(route('procurement.rfqs.show', $rfq))->assertSee('Abstract of Quotations (Awarded)')->assertDontSee('id="btn_cancel"', false);

        $other = User::factory()->create(['is_activated' => 1]);
        $other->givePermissionTo('manage rfq');
        $this->actingAs($other)->get(route('procurement.aoqs.index'))->assertForbidden();
        $this->get(route('procurement.rfqs.show', $rfq))->assertOk()->assertDontSee('Abstract of Quotations');

        // Deleting the abstract deletes its PDFs
        $this->actingAs($this->bac)->postJson(route('procurement.aoqs.reopen', $aoq))->assertOk();
        $this->deleteJson(route('procurement.aoqs.destroy', $aoq))->assertOk();
        Storage::disk('local')->assertMissing([$alpha->attachment_path, $hddQuote->attachment_path]);
    }

    public function test_the_twg_returns_it_to_the_bac_and_a_changed_offer_is_evaluated_again(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800', $ram => '2900']);
        $twg = $this->twgMember();

        $this->refused(fn () => $this->aoqs->sendToTwg($aoq, $this->bac, $this->bac), 'must be a TWG member');
        $this->aoqs->sendToTwg($aoq, $this->bac, $twg);
        $this->assertSame(['Jay Dizon', 'Engineer III'], [$aoq->fresh()->evaluated_by_name, $aoq->fresh()->evaluated_by_designation]);   // the "Evaluated by" signatory
        $this->refused(fn () => $this->quote($aoq, 'Late Bidder', [$hdd => '1']), 'reopen it');
        $this->refused(fn () => $this->aoqs->returnToBac($aoq, $this->twgMember('Not', 'Assigned'), 'x'), 'Only the TWG members assigned');

        // Partly evaluated, then returned: the findings are kept
        $quotes = $aoq->quotes()->get()->keyBy('rfq_item_id');
        $this->aoqs->saveEvaluation($aoq, $twg, [$quotes[$hdd]->id => ['is_compliant' => '1']], null);
        $this->refused(fn () => $this->aoqs->completeEvaluation($aoq, $twg, [], null), '1 offer(s) still have no finding');
        $aoq = $this->aoqs->returnToBac($aoq, $twg, 'RAM spec sheet missing');
        $this->assertSame([AoqStatus::Draft, 'RAM spec sheet missing'], [$aoq->status, $aoq->return_reason]);
        $this->assertSame(1, $this->bac->notifications()->count());

        // The BAC changes the RAM offer: only that finding is cleared
        $alpha = $aoq->suppliers()->first();
        $this->aoqs->saveSupplier($aoq, $this->bac, ['name' => 'Alpha Trading', 'address' => 'Manila'],
            [$hdd => ['unit_price' => '4800'], $ram => ['unit_price' => '2800']], $alpha->id);
        $this->assertTrue($quotes[$hdd]->fresh()->is_compliant);
        $this->assertNull($quotes[$ram]->fresh()->is_compliant);
    }

    public function test_the_end_user_is_the_evaluator_when_a_twg_member(): void
    {
        $requester = $this->twgMember('Bren', 'Tique');   // the PRs' "Requested by"
        $aoq = $this->aoqs->start($this->issuedRfq(), $this->bac);

        $this->assertSame([$requester->id], $aoq->evaluators()->pluck('users.id')->all());
        $this->assertSame(['Bren Tique', 'Engineer III'], $this->aoqs->signatories($aoq->fresh())['evaluated_by']);
    }

    public function test_the_request_shows_its_status_trail(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd] = $this->itemIds($rfq);
        $pr = $rfq->items()->first()->line->request;
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800']);
        $this->aoqs->award($this->evaluated($aoq), $this->bac);

        $this->assertSame(
            ['created', 'submitted', 'rfq_added', 'rfq_issued', 'aoq_started', 'twg_sent', 'twg_evaluated', 'aoq_awarded'],
            $pr->events()->pluck('event')->all()
        );
        $this->assertStringContainsString('Hard Drive (4 TB): Alpha Trading (₱9,600.00)', $pr->events()->where('event', 'aoq_awarded')->value('description'));

        $this->staff->givePermissionTo('manage requests');
        // On the request's page: a Status button and the PPMP's details, each in a modal
        $this->actingAs($this->staff)->get(route('procurement.requests.show', $pr))->assertOk()
            ->assertSee(route('procurement.requests.trail', $pr), false)->assertSee(route('procurement.requests.ppmp', $pr), false)->assertDontSee('id="pr_trail"', false);
        $this->get(route('procurement.requests.trail', $pr))->assertOk()->assertSee('sent to the TWG for technical evaluation (Jay Dizon)')->assertSee('RAM 16 GB: failed');
        $this->get(route('procurement.requests.ppmp', $pr))->assertOk()->assertSee('PPMP_DETAILS_MODAL', false)->assertSee('Hard Drive (4 TB)')
            ->assertSee('is charged here')->assertSee('Open PPMP page');
        $this->get(route('procurement.requests.show', $pr))->assertSee(route('procurement.requests.rfq', $pr), false);
        $this->get(route('procurement.requests.rfq', $pr))->assertOk()->assertSee('RFQ_DETAILS_MODAL', false)->assertSee($rfq->title())
            ->assertSee('Quotations due')->assertDontSee('Open RFQ page');   // the office: details only

        // The trail's RFQ and AOQ open read-only for the office, which has no RFQ / AOQ access
        $this->assertFalse($this->staff->canAccessPermission(Aoq::MANAGE_PERMISSION));
        $this->get(route('procurement.requests.trail', $pr))->assertOk()
            ->assertSee(route('procurement.requests.rfq-view', [$pr, $rfq]), false)->assertSee(route('procurement.requests.aoq-view', [$pr, $aoq]), false)
            ->assertDontSee(route('procurement.aoqs.show', $aoq), false);
        $this->get(route('procurement.requests.rfq-view', [$pr, $rfq]))->assertOk()->assertSee('RFQ_DETAILS_MODAL', false);
        $this->get(route('procurement.requests.aoq-view', [$pr, $aoq]))->assertOk()->assertSee('AOQ_DETAILS_MODAL', false)
            ->assertSee('Alpha Trading')->assertSee('Awarded')->assertSee('this request')->assertSee('Meets specs')->assertDontSee('Open AOQ page');

        // Not on this request's trail: refused
        $otherPr = $this->submittedPr([[$this->ram, 1]]);
        $otherRfq = $this->rfqs->issue($this->rfqs->addLines($this->draftRfq(), $this->bac, $otherPr->items->pluck('id')->all()), $this->bac,
            ['submission_deadline' => '2026-10-20 10:00', 'signatory_name' => 'Ana Santos']);
        $otherAoq = $this->aoqs->start($otherRfq, $this->bac);
        $this->get(route('procurement.requests.rfq-view', [$pr, $otherRfq]))->assertNotFound();
        $this->get(route('procurement.requests.aoq-view', [$pr, $otherAoq]))->assertNotFound();

        // An AOQ not awarded: viewable on the trail only once its quotations are opened (Oct 20, 10:00 here; now is Oct 16)
        $this->get(route('procurement.requests.trail', $otherPr))->assertOk()->assertDontSee(route('procurement.requests.aoq-view', [$otherPr, $otherAoq]), false);
        $this->get(route('procurement.requests.aoq-view', [$otherPr, $otherAoq]))->assertForbidden();
        Carbon::setTestNow('2026-10-20 10:30:00');
        $this->get(route('procurement.requests.trail', $otherPr))->assertSee(route('procurement.requests.aoq-view', [$otherPr, $otherAoq]), false);
        $this->get(route('procurement.requests.aoq-view', [$otherPr, $otherAoq]))->assertOk()->assertSee('AOQ_DETAILS_MODAL', false)->assertSee('No quotations recorded yet.');

        // From the list: a Trail button opening it in a modal
        $this->get(route('procurement.requests.index'))->assertOk()->assertSee(route('procurement.requests.trail', $pr), false);
        $this->get(route('procurement.requests.trail', $pr))->assertOk()->assertSee('REQUEST_TRAIL_MODAL', false)
            ->assertSee('Now:')->assertSee('awarded')->assertDontSee('id="pr_trail"', false);
    }
}
