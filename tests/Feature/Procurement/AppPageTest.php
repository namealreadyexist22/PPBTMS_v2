<?php

namespace Tests\Feature\Procurement;

use App\Enums\AppStatus;
use App\Enums\Region;
use App\Models\Procurement\AnnualProcurementPlan;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\AppService;
use App\Services\Procurement\DivisionPpmpService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class AppPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $bacSec;
    protected User $chair;
    protected User $hope;
    protected User $visSec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);
        Permission::firstOrCreate(['name' => AppService::MANAGE_PERMISSION, 'guard_name' => 'web']);

        $this->bacSec = User::factory()->create(['is_activated' => 1, 'fname' => 'Jenny Lou', 'minitial' => 'R', 'lname' => 'Flores', 'designation' => 'Head - Secretariat', 'region' => 'lm']);
        $this->chair = User::factory()->create(['is_activated' => 1, 'fname' => 'Ronald', 'minitial' => 'E', 'lname' => 'Rimando', 'designation' => 'Chairperson', 'region' => 'lm']);
        $this->hope = User::factory()->create(['is_activated' => 1, 'fname' => 'Pablo Luis', 'minitial' => 'S', 'lname' => 'Azcona', 'designation' => 'Administrator/CEO']);
        $this->visSec = User::factory()->create(['is_activated' => 1, 'region' => 'vis']);

        foreach ([$this->bacSec, $this->chair, $this->hope, $this->visSec] as $user) {
            $user->givePermissionTo('manage app');
        }
        $this->bacSec->givePermissionTo(AppService::MANAGE_PERMISSION);
        $this->visSec->givePermissionTo(AppService::MANAGE_PERMISSION);

        // Approved LM Division PPMPs from two offices
        $head = User::factory()->create();
        $ppmps = app(PpmpService::class);
        foreach ([['06030', 'BUDGET AND TREASURY DIVISION', 'Supply and delivery of monthly Refreshment Supply', '12000', 'DAS'],
                  ['01000', 'OFFICE OF THE BOARD', 'Printing of tarpaulin for the CSR Year-End Review', '600', 'DAS'],
                  ['01000', 'OFFICE OF THE BOARD', 'Procurement of Various Office Supplies available at PS-DBM', '3109949.08', 'PS']] as $i => [$code, $name, $description, $budget, $mode]) {
            $office = Office::firstOrCreate(['code' => $code], ['name' => $name, 'head_user_id' => $head->id]);
            $staff = User::factory()->create(['office_id' => $office->id]);
            $ppmp = $office->ppmps()->first() ?? $ppmps->create($office, 2026, $staff);
            $pap = $ppmp->paps()->first() ?? $ppmps->addPap($ppmp, "26-{$code}-01", 'Management and supervision of the BTDs operations');
            $ppmps->addItem($ppmp, ['ppmp_pap_id' => $pap->id, 'description' => $description, 'project_type' => 'goods',
                'procurement_mode_id' => ProcurementMode::where('code', $mode)->value('id'), 'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
                'proc_start' => '2026-01-01', 'proc_end' => '2026-12-01', 'estimated_budget' => $budget]);
        }
        foreach (Office::all() as $office) {
            $ppmp = $office->ppmps()->first();
            $ppmps->submit($ppmp, $head);
            app(DivisionPpmpService::class)->approve($office, 2026, Region::Lm, $head, $head);
        }
    }

    public function test_bac_secretariat_prepares_chair_recommends_hope_approves(): void
    {
        // Signatories and creating the APP: BAC Secretariat of the region only
        $this->actingAs($this->bacSec)->get(route('procurement.app.index', ['fy' => 2026]))->assertOk()
            ->assertSee('APP FY 2026 — Luzon/Mindanao')->assertSee('Create APP')->assertSee('Regional Bids and Awards Committee');
        $this->postJson(route('procurement.app.signatories'), ['region' => 'lm', 'prepared' => $this->bacSec->id, 'recommended' => $this->chair->id, 'approved' => $this->hope->id])->assertOk();
        $this->postJson(route('procurement.app.store'), ['fiscal_year' => 2026, 'region' => 'vis', 'type' => 'final'])->assertForbidden();
        $this->postJson(route('procurement.app.store'), ['fiscal_year' => 2026, 'region' => 'lm', 'type' => 'final'])->assertOk();
        $app = AnnualProcurementPlan::sole();

        // Workspace: unassigned projects -> lines
        $this->get(route('procurement.app.show', $app))->assertOk()
            ->assertSee('PPMP projects not yet in the APP (3)')->assertSee('Printing of tarpaulin for the CSR Year-End Review');
        $this->postJson(route('procurement.app.generate', $app))->assertOk();
        $this->get(route('procurement.app.show', $app))->assertSee('All projects of the approved Luzon/Mindanao Division PPMPs are in the APP')
            ->assertSee('26-06030-01: Management and supervision of the BTDs operations')
            ->assertSee('Common Use Supplies and Equipment (CSE) to be purchased from PS-DBM');

        // Group two lines, edit the result, split it again, group again
        $ids = $app->items()->where('is_cse', false)->pluck('id')->all();
        $this->postJson(route('procurement.app.group', $app), ['line_ids' => [$ids[0]]])->assertStatus(422);
        $this->postJson(route('procurement.app.group', $app), ['line_ids' => $ids])->assertOk();
        $line = $app->items()->where('is_cse', false)->sole();
        $this->assertEquals('12600.00', $line->estimated_budget);

        $this->get(route('procurement.app.lines.entry', [$app, 'id' => $line->id]))->assertOk()->assertSee('PPMP projects in this line');
        $this->postJson(route('procurement.app.lines.store', $app), [
            'id' => $line->id, 'pap_code' => '26-06030-01', 'pap_title' => 'Management and supervision of the BTDs operations',
            'project_title' => 'Refreshments and tarpaulin printing', 'end_user' => 'Budget and Treasury Division; Office of the Board',
            'description' => 'Refreshments and tarpaulin - Goods', 'procurement_mode_id' => ProcurementMode::where('code', 'DAS')->value('id'),
            'early_procurement' => 1, 'bid_criteria' => 'LCRB', 'proc_start' => '2026-01', 'proc_end' => '2026-12',
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'), 'procurement_strategy' => 'N/A',
        ])->assertOk();
        $this->postJson(route('procurement.app.lines.store', $app), ['id' => $line->id, 'project_title' => ''])->assertStatus(422)->assertJsonValidationErrors(['project_title', 'end_user']);

        // Submit -> Chair -> HOPE (others are refused)
        $this->postJson(route('procurement.app.submit', $app))->assertOk();
        $this->postJson(route('procurement.app.recommend', $app))->assertStatus(422);
        $this->actingAs($this->chair)->get(route('procurement.app.show', $app))->assertSee('id="btn_recommend"', false);
        $this->postJson(route('procurement.app.return', $app), [])->assertStatus(422);
        $this->postJson(route('procurement.app.recommend', $app), ['remarks' => 'OK'])->assertOk();
        $this->actingAs($this->hope)->get(route('procurement.app.show', $app))->assertSee('id="btn_approve"', false);
        $this->postJson(route('procurement.app.approve', $app))->assertOk();
        $this->assertSame(AppStatus::Approved, $app->fresh()->status);

        // Print matches the APP form
        $this->get(route('procurement.app.print', $app))->assertOk()
            ->assertSee('ANNUAL PROCUREMENT PLAN FOR FY 2026')
            ->assertSee('UPDATED [Version No.', false)
            ->assertSee('26-06030-01: Management and supervision of the BTDs operations')
            ->assertSee('Refreshments and tarpaulin printing')
            ->assertSee('1/2026')
            ->assertSee('Corporate Operating Budget')
            ->assertSee('₱12,600.00')
            ->assertSee('Total Amount of Estimated Budget for EPA Projects:')
            ->assertSee('3,109,949.08')
            ->assertSee('3,122,549.08')
            ->assertSee('Jenny Lou R. Flores')
            ->assertSee('By the Authority of the Bids and Awards Committee:')
            ->assertSee('Ronald E. Rimando')
            ->assertSee('Pablo Luis S. Azcona')
            ->assertSee('Head of the Procuring Entity')
            ->assertDontSee('class="watermark"', false);

        // Updated version
        $this->actingAs($this->bacSec)->postJson(route('procurement.app.update-version', $app))->assertOk();
        $this->get(route('procurement.app.print', AnnualProcurementPlan::where('version', 2)->sole()))
            ->assertSee('<span class="blank">2</span>', false)->assertSee('DRAFT');
    }

    public function test_only_the_regions_secretariat_edits(): void
    {
        $app = app(AppService::class)->create(2026, Region::Lm, $this->bacSec);

        $this->actingAs($this->visSec)->postJson(route('procurement.app.generate', $app))->assertForbidden();
        $this->actingAs($this->chair)->postJson(route('procurement.app.generate', $app))->assertForbidden();
        $this->get(route('procurement.app.show', $app))->assertOk()->assertDontSee('Add all as lines');
        $this->actingAs($this->visSec)->postJson(route('procurement.app.signatories'), ['region' => 'lm'])->assertForbidden();
    }
}
