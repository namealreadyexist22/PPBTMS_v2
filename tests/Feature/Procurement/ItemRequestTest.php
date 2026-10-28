<?php

namespace Tests\Feature\Procurement;

use App\Enums\ItemRequestStatus;
use App\Enums\ProjectType;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Item;
use App\Models\Procurement\ItemCategory;
use App\Models\Procurement\ItemRequest;
use App\Models\Procurement\Office;
use App\Models\Procurement\PpmpItemDistribution;
use App\Models\Procurement\ProcurementMode;
use App\Models\Procurement\Unit;
use App\Models\User;
use App\Services\Procurement\ItemRequestService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ItemRequestTest extends TestCase
{
    use RefreshDatabase;

    protected Office $mis;
    protected Office $sred;
    protected User $misStaff;
    protected User $sredStaff;
    protected Item $desktop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);

        $dept = Office::create(['type' => 'department', 'acronym' => 'PPSPD', 'name' => 'Planning Department', 'budget_fund' => 'regular']);
        $this->mis = Office::create(['type' => 'section', 'code' => '05012', 'acronym' => 'MIS', 'name' => 'MIS Section', 'parent_id' => $dept->id]);
        $this->sred = Office::create(['type' => 'division', 'code' => '07000', 'acronym' => 'SRED', 'name' => 'Sugarcane Research', 'parent_id' => $dept->id]);

        $this->misStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->mis->id]);
        $this->sredStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->sred->id]);
        foreach ([$this->misStaff, $this->sredStaff] as $u) {
            $u->givePermissionTo('manage ppmp');
        }

        // ICT Equipment: procured by MIS only
        $ict = ItemCategory::create(['code' => 'ICTX', 'name' => 'ICT Equipment', 'restricted_office_id' => $this->mis->id, 'is_active' => true]);
        $this->desktop = Item::create(['code' => 'ICTX-0001', 'name' => 'Desktop Computer', 'item_category_id' => $ict->id, 'unit_id' => Unit::first()->id,
            'project_type' => 'goods', 'standard_unit_cost' => '90000', 'specifications' => "Memory: 16GB DDR5\nStorage: 512GB SSD", 'is_active' => true]);
    }

    /** MIS's FY 2027 PPMP with a desktop project of $quantity. */
    protected function misProject(int $quantity)
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->mis, 2027, $this->misStaff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');

        return $service->addItem($ppmp, [
            'ppmp_pap_id' => $pap->id, 'item_id' => $this->desktop->id, 'description' => 'Desktop Computers', 'project_type' => ProjectType::Goods,
            'quantity' => $quantity, 'unit_id' => Unit::first()->id, 'unit_cost' => '90000', 'quantity_size' => null,
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'), 'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'allotment_class' => 'co', 'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'delivery_period' => 'May 2027', 'estimated_budget' => (string) ($quantity * 90000),
        ]);
    }

    public function test_an_office_requests_and_the_procuring_office_approves_part_into_its_distribution(): void
    {
        $project = $this->misProject(4);
        PpmpItemDistribution::create(['line_uuid' => $project->line_uuid, 'office_id' => $this->mis->id, 'quantity' => 2]);

        // SRED asks MIS for 3 desktops
        $this->actingAs($this->sredStaff)->get(route('procurement.item-requests.entry'))->assertOk()->assertSee('ICTX-0001 — Desktop Computer')->assertSee('Procured by MIS');
        $this->postJson(route('procurement.item-requests.store'), ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 3, 'purpose' => 'Field staff'])
            ->assertOk()->assertJsonPath('message', 'IR 2027-0001 sent to MIS.');
        $ir = ItemRequest::sole();
        $this->assertSame($this->mis->id, $ir->procuring_office_id);
        $this->assertSame(1, $this->misStaff->notifications()->count());
        $this->get(route('procurement.item-requests.decide-entry', $ir))->assertForbidden();   // SRED cannot decide

        // MIS: its incoming list and totals; the project has room for 2
        $this->actingAs($this->misStaff)->get(route('procurement.item-requests.index', ['tab' => 'incoming']))->assertOk()
            ->assertSee('IR 2027-0001')->assertSee('Requested from your office, per item')->assertSee('Decide')
            ->assertSeeInOrder(['>Requestor<', '>Department<', 'IR 2027-0001', $this->sredStaff->fresh()->fullname, 'PPSPD', 'Desktop Computer'], false);
        // As the page fetches it to redraw the list in place (fixed tabs with the pending count)
        $this->get(route('procurement.item-requests.index', ['tab' => 'incoming']), ['X-Requested-With' => 'XMLHttpRequest'])->assertOk()
            ->assertSee('id="ir_view"', false)->assertSee('id="ir_tabs"', false)->assertSee('data-tab="incoming"', false)->assertSeeInOrder(['js-pending', '1</span>'], false);
        $this->get(route('procurement.item-requests.decide-entry', $ir))->assertOk()->assertSee('room for 2')->assertSee('16GB DDR5');
        $this->postJson(route('procurement.item-requests.decide', $ir), ['approved_quantity' => 3, 'ppmp_item_id' => $project->id])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'has room for 2 more'));
        $this->postJson(route('procurement.item-requests.decide', $ir), ['approved_quantity' => 1])
            ->assertStatus(422)->assertJsonPath('message', fn ($m) => str_contains($m, 'Choose the PPMP project'));

        // 1 of 3: SRED's share in the distribution
        $this->postJson(route('procurement.item-requests.decide', $ir), ['approved_quantity' => 1, 'ppmp_item_id' => $project->id, 'decision_remarks' => 'Two still serviceable'])
            ->assertOk()->assertJsonPath('message', 'IR 2027-0001: Partly approved.');
        $ir->refresh();
        $this->assertSame(ItemRequestStatus::Partial, $ir->status);
        $this->assertSame($project->line_uuid, $ir->line_uuid);
        $this->assertEquals(1, PpmpItemDistribution::where('line_uuid', $project->line_uuid)->where('office_id', $this->sred->id)->value('quantity'));
        $this->assertSame(1, $this->sredStaff->notifications()->count());
        $this->assertSame('MIS approved 1 of your request for 3 Desktop Computer (IR 2027-0001): Two still serviceable', $this->sredStaff->notifications()->first()->data['message']);
        $this->refused(fn () => app(ItemRequestService::class)->decide($ir, $this->misStaff, 1, $project->id, null), 'already Partly approved');

        // A second request adds to SRED's share; 0 declines
        $second = app(ItemRequestService::class)->create($this->sredStaff, $this->sred, ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 1]);
        app(ItemRequestService::class)->decide($second, $this->misStaff, 1, $project->id, null);
        $this->assertEquals(2, PpmpItemDistribution::where('line_uuid', $project->line_uuid)->where('office_id', $this->sred->id)->value('quantity'));
        $third = app(ItemRequestService::class)->create($this->sredStaff, $this->sred, ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 5]);

        // Declined: only with a reason, nothing to the distribution, the office told it was declined
        $this->get(route('procurement.item-requests.decide-entry', $third))->assertSee('js-decline', false)->assertSee('Required when declining.');
        $this->postJson(route('procurement.item-requests.decide', $third), ['approved_quantity' => 0])
            ->assertStatus(422)->assertJsonPath('message', 'State why the request is declined.');
        $this->postJson(route('procurement.item-requests.decide', $third), ['approved_quantity' => 0, 'decision_remarks' => 'No budget'])
            ->assertOk()->assertJsonPath('message', 'IR 2027-0003: Declined.');
        $this->assertSame(ItemRequestStatus::Declined, $third->fresh()->status);
        $this->assertEquals(2, PpmpItemDistribution::where('line_uuid', $project->line_uuid)->where('office_id', $this->sred->id)->value('quantity'));
        $declined = $this->sredStaff->notifications()->get()->firstWhere('data.title', 'Item request Declined');
        $this->assertSame('MIS declined your request for 5 Desktop Computer (IR 2027-0003): No budget', $declined->data['message']);
        $this->assertSame('fas fa-times-circle', $declined->data['icon']);

        // The list: no purpose / decision column; the details are in the modal; MIS shown under its department
        $this->actingAs($this->sredStaff)->get(route('procurement.item-requests.index'))->assertOk()->assertSee('Partly approved')
            ->assertSeeInOrder(['>Date<', '>To<', 'IR 2027-0001', '>MIS<', 'Desktop Computer'], false)->assertDontSee('FY 2027 ·')
            ->assertDontSee('Purpose / decision')->assertSee(route('procurement.item-requests.show', $ir), false);
        $this->get(route('procurement.item-requests.show', $ir))->assertOk()->assertSee('ITEM_DETAILS_MODAL', false)
            ->assertSee('Field staff')->assertSee('1 of 3 approved by MIS')->assertSee('Two still serviceable')->assertSee('Desktop Computers')
            ->assertSee('16GB DDR5')->assertDontSee('data-then="js-decide"', false);
        $this->get(route('procurement.item-requests.show', $third))->assertOk()->assertSee('Declined by MIS')->assertSee('Reason')->assertSee('No budget');
        $this->actingAs($this->misStaff)->get(route('procurement.item-requests.show', $ir))->assertOk();
        $outsider = User::factory()->create(['is_activated' => 1, 'office_id' => Office::create(['code' => '09000', 'acronym' => 'LEGAL', 'name' => 'Legal'])->id]);
        $outsider->givePermissionTo('manage ppmp');
        $this->actingAs($outsider)->get(route('procurement.item-requests.show', $ir))->assertForbidden();
    }

    public function test_only_items_another_office_procures_and_only_pending_ones_cancel(): void
    {
        $service = app(ItemRequestService::class);
        $this->assertTrue($service->requestableItems($this->sred)->contains('id', $this->desktop->id));
        $this->assertFalse($service->requestableItems($this->mis)->contains('id', $this->desktop->id));   // MIS procures it itself
        $this->refused(fn () => $service->create($this->misStaff, $this->mis, ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 1]), 'another office procures');

        $ir = $service->create($this->sredStaff, $this->sred, ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 2]);
        $this->actingAs($this->misStaff)->postJson(route('procurement.item-requests.cancel', $ir))->assertForbidden();
        $this->actingAs($this->sredStaff)->postJson(route('procurement.item-requests.cancel', $ir))->assertOk();
        $this->assertSame(ItemRequestStatus::Cancelled, $ir->fresh()->status);
        $this->postJson(route('procurement.item-requests.cancel', $ir))->assertStatus(422);
    }

    public function test_a_request_keeps_the_item_as_when_requested_when_the_twg_changes_it(): void
    {
        $ir = app(ItemRequestService::class)->create($this->sredStaff, $this->sred, ['item_id' => $this->desktop->id, 'fiscal_year' => 2027, 'quantity' => 2]);
        $this->assertSame(['ICTX-0001', 'Desktop Computer', "Memory: 16GB DDR5\nStorage: 512GB SSD"], [$ir->item_code, $ir->item_name, $ir->specifications]);
        $this->assertEquals('90000.00', $ir->unit_cost);

        // The TWG raises the specs and renames the item; the request still shows what was asked for
        $this->desktop->update(['name' => 'Desktop Computer (2028 spec)', 'specifications' => "Memory: 32GB DDR5\nStorage: 1TB SSD", 'standard_unit_cost' => '110000']);
        $ir->refresh();
        $this->assertTrue($ir->specsChanged());
        $this->actingAs($this->sredStaff)->get(route('procurement.item-requests.show', $ir))->assertOk()
            ->assertSee('ICTX-0001 — Desktop Computer')->assertDontSee('2028 spec')->assertSee('₱90,000.00')
            ->assertSeeInOrder(['Specifications (TWG) when requested', '16GB DDR5', 'changed this item', 'Current specifications (TWG)', '32GB DDR5'])
            ->assertSee('data-bs-toggle="collapse"', false);   // folded: opened on click
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
}
