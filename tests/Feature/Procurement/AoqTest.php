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

    public function test_each_item_goes_to_its_lowest_responsive_quote_within_the_abc(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->assertSame($aoq->id, $this->aoqs->start($rfq, $this->bac)->id);   // one per RFQ

        $this->quote($aoq, 'Alpha Trading', [$hdd => '4800', $ram => '2900']);
        $this->quote($aoq, 'Beta Supply', [$hdd => '4500', $ram => '3100']);       // RAM over ABC (12,400 > 12,000)
        $this->quote($aoq, 'Gamma Corp', [$hdd => '4000', $ram => '2000'], ['is_responsive' => false, 'remarks' => 'No PhilGEPS']);
        $this->quote($aoq, 'Delta Inc', [$hdd => '4100', $ram => '2500'], [], [$ram => ['is_compliant' => false]]);

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

    public function test_a_tie_is_picked_by_the_bac_and_an_item_without_a_counting_quote_fails(): void
    {
        $rfq = $this->issuedRfq();
        [$hdd, $ram] = $this->itemIds($rfq);
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->quote($aoq, 'Alpha Trading', [$hdd => '4500', $ram => '9000']);
        $this->quote($aoq, 'Beta Supply', [$hdd => '4500']);

        $this->refused(fn () => $this->aoqs->award($aoq, $this->bac), 'has a tie');

        $beta = $aoq->suppliers()->where('name', 'Beta Supply')->value('id');
        $this->aoqs->award($aoq, $this->bac, [$hdd => $beta]);
        $rows = $this->aoqs->evaluate($aoq->fresh())->keyBy(fn ($r) => $r['item']->id);
        $this->assertSame($beta, $rows[$hdd]['awarded']);
        $this->assertNull($rows[$ram]['awarded']);
        $this->assertSame([], $rows[$ram]['lowest']);
    }

    public function test_starting_takes_the_bac_from_the_officials_and_needs_an_issued_rfq(): void
    {
        [$chair, $m1] = User::factory()->count(2)->create(['is_activated' => 1]);
        app(OfficialsService::class)->set(Region::Lm, ['bac_chair' => $chair->id], [$m1->id]);

        $this->refused(fn () => $this->aoqs->start($this->draftRfq(), $this->bac), 'only an issued RFQ');

        $rfq = $this->issuedRfq();
        $aoq = $this->aoqs->start($rfq, $this->bac);
        $this->assertSame($chair->fresh()->fullname, $aoq->bac_chair_name);
        $this->assertSame([$m1->fresh()->fullname], $aoq->bac_members);

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
        $this->postJson(route('procurement.aoqs.suppliers.store', $aoq), [
            'name' => 'Alpha Trading', 'is_responsive' => 1,
            'quotes' => [$hdd => ['unit_price' => '4800', 'brand' => 'WD Red', 'is_compliant' => 1], $ram => ['unit_price' => '', 'is_compliant' => 1]],
        ])->assertOk();
        $this->postJson(route('procurement.aoqs.suppliers.store', $aoq), ['name' => 'alpha trading', 'is_responsive' => 1])
            ->assertStatus(422)->assertJsonPath('message', 'alpha trading is already on this abstract.');

        $this->get($url)->assertOk()->assertSee('Alpha Trading')->assertSee('9,600.00')->assertSee('No quote')->assertSee('fewer than 3');
        $this->postJson(route('procurement.aoqs.header', $aoq), ['bac_chair_name' => 'Juan Cruz', 'bac_members' => ['Ana Reyes', '', null]])->assertOk();
        $this->assertSame(['Ana Reyes'], $aoq->fresh()->bac_members);

        $this->postJson(route('procurement.aoqs.award', $aoq))->assertOk();
        $this->get(route('procurement.aoqs.print', $aoq))->assertOk()->assertSee('ABSTRACT OF QUOTATIONS')->assertSee('Juan Cruz')->assertSee('Failed');
        $this->get(route('procurement.rfqs.show', $rfq))->assertSee('Abstract of Quotations (Awarded)')->assertDontSee('id="btn_cancel"', false);

        $other = User::factory()->create(['is_activated' => 1]);
        $other->givePermissionTo('manage rfq');
        $this->actingAs($other)->get(route('procurement.aoqs.index'))->assertForbidden();
        $this->get(route('procurement.rfqs.show', $rfq))->assertOk()->assertDontSee('Abstract of Quotations');
    }
}
