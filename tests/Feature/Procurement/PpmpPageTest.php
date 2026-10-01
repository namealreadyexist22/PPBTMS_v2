<?php

namespace Tests\Feature\Procurement;

use App\Enums\PpmpStatus;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The PPMP details page end to end: create, add projects, submit, return,
 * resubmit, approve, amend; plus who may see and do what.
 */
class PpmpPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $head;
    protected User $outsider;
    protected Office $section;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);

        $this->head = User::factory()->create(['is_activated' => 1, 'designation' => 'Division Chief']);
        $division = Office::create(['code' => '05010', 'name' => 'PPPD', 'head_user_id' => $this->head->id]);
        $this->section = Office::create(['code' => '05011', 'name' => 'Planning Section', 'parent_id' => $division->id]);
        $other = Office::create(['code' => '09000', 'name' => 'GAD']);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section->id]);
        $this->outsider = User::factory()->create(['is_activated' => 1, 'office_id' => $other->id]);

        foreach ([$this->staff, $this->head, $this->outsider] as $user) {
            $user->givePermissionTo('manage ppmp');
        }
    }

    protected function project(array $overrides = []): array
    {
        return array_merge([
            'description'         => 'Office supplies for the Planning Section',
            'project_type'        => 'goods',
            'quantity'            => 1,
            'quantity_size'       => '1 lot',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id'      => FundSource::where('code', 'GAA')->value('id'),
            'proc_start'          => '2027-01',
            'proc_end'            => '2027-02',
            'delivery_period'     => 'March 2027',
            'estimated_budget'    => '150,000.00',
            'pre_proc_conference' => 1,
        ], $overrides);
    }

    public function test_full_workflow_through_the_page(): void
    {
        // Create -> redirected to its page
        $response = $this->actingAs($this->staff)->postJson(route('procurement.ppmp.store'), [
            'office_id' => $this->section->id, 'fiscal_year' => now()->year + 1, 'type' => 'indicative',
        ])->assertOk();
        $ppmp = Ppmp::where('uuid', $response->json('uuid'))->sole();
        $this->assertSame(route('procurement.ppmp.show', $ppmp), $response->json('url'));

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()
            ->assertSee('Add Project')->assertSee('Submit for Approval')->assertSee('No procurement projects yet');

        // Add, validate, edit, remove projects
        $this->get(route('procurement.ppmp.items.entry', $ppmp))->assertOk()->assertSee('Add Procurement Project');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project())->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project(['proc_end' => '2026-12', 'estimated_budget' => '0']))
            ->assertStatus(422)->assertJsonValidationErrors(['proc_end', 'estimated_budget']);

        $item = $ppmp->items()->first();
        $this->assertSame('2027-01-01', $item->proc_start->toDateString());
        $this->assertEquals('150000.00', $ppmp->fresh()->total_budget);

        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'id' => $item->id]))->assertOk()->assertSee('value="2027-01"', false);
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project(['id' => $item->id, 'estimated_budget' => '120000']))->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project(['description' => 'Laptops', 'estimated_budget' => '80000']))->assertOk();
        $this->assertEquals('200000.00', $ppmp->fresh()->total_budget);

        $laptops = $ppmp->items()->where('description', 'Laptops')->first();
        $this->deleteJson(route('procurement.ppmp.items.destroy', $ppmp), ['id' => $laptops->id])->assertOk();
        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('Office supplies for the Planning Section')->assertSee('120,000.00');

        // Submit -> locked for the office, approve buttons only for the division head
        $this->postJson(route('procurement.ppmp.submit', $ppmp))->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project())->assertStatus(422);
        $this->get(route('procurement.ppmp.show', $ppmp))->assertDontSee('Add Project')->assertDontSee('id="btn_approve"', false);
        $this->postJson(route('procurement.ppmp.approve', $ppmp))->assertStatus(422);   // staff cannot approve

        // Head returns with a reason (required), office fixes and resubmits
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('id="btn_approve"', false);
        $this->postJson(route('procurement.ppmp.return', $ppmp), [])->assertStatus(422);
        $this->postJson(route('procurement.ppmp.return', $ppmp), ['remarks' => 'Split the lot'])->assertOk();

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $ppmp))->assertSee('Split the lot')->assertSee('Add Project');
        $this->postJson(route('procurement.ppmp.submit', $ppmp))->assertOk();

        $this->actingAs($this->head)->postJson(route('procurement.ppmp.approve', $ppmp), ['remarks' => 'OK'])->assertOk();
        $this->assertSame(PpmpStatus::Approved, $ppmp->fresh()->status);

        // Amend -> new draft version, linked from the page
        $response = $this->actingAs($this->staff)->postJson(route('procurement.ppmp.amend', $ppmp))->assertOk();
        $amendment = Ppmp::where('amended_from_id', $ppmp->id)->sole();
        $this->assertSame(route('procurement.ppmp.show', $amendment), $response->json('url'));
        $this->get(route('procurement.ppmp.show', $ppmp))->assertDontSee('id="btn_amend"', false);   // one open amendment at a time
        $this->get(route('procurement.ppmp.show', $amendment))->assertOk()->assertSee('Add Project');
    }

    public function test_other_offices_cannot_view_or_change(): void
    {
        $ppmp = app(\App\Services\Procurement\PpmpService::class)->create($this->section, 2027, $this->staff);

        $this->actingAs($this->outsider)->get(route('procurement.ppmp.show', $ppmp))->assertForbidden();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project())->assertForbidden();
        $this->postJson(route('procurement.ppmp.submit', $ppmp))->assertForbidden();

        // Head can view the section's PPMP but not edit it
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertDontSee('Add Project');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project())->assertForbidden();
    }

    public function test_delete_draft_needs_permission_and_frees_the_number(): void
    {
        $service = app(\App\Services\Procurement\PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $ppmp))->assertDontSee('id="btn_delete_ppmp"', false);
        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertForbidden();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'menu.ppmp-destroy', 'guard_name' => 'web']);
        $this->staff->givePermissionTo('menu.ppmp-destroy');

        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('id="btn_delete_ppmp"', false);
        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertOk();
        $this->assertSame(0, Ppmp::withTrashed()->count());

        // Same office + year can start over with the same number
        $this->assertSame('27-05011-01', $service->create($this->section, 2027, $this->staff)->ppmp_no);
    }
}
