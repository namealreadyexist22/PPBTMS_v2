<?php

namespace Tests\Feature\Procurement;

use App\Enums\PpmpStatus;
use App\Models\Procurement\DivisionPpmp;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PPMP screens end to end: a section builds its PPMP (PAPs + projects) and submits;
 * the division head returns or approves it on the Division PPMP page (PPMP No. 1);
 * an amendment approved later becomes PPMP No. 2.
 */
class PpmpPageTest extends TestCase
{
    use RefreshDatabase;

    protected User $staff;
    protected User $staff2;
    protected User $head;
    protected User $oic;
    protected User $outsider;
    protected Office $division;
    protected Office $section;
    protected Office $section2;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed([RolePermissionSeeder::class, ProcurementLookupSeeder::class]);

        $this->head = User::factory()->create(['is_activated' => 1, 'fname' => 'Digna', 'minitial' => 'D', 'lname' => 'Gonzales', 'designation' => 'Manager III, PPSPD']);
        $this->division = Office::create(['code' => '05010', 'name' => 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'head_user_id' => $this->head->id, 'is_consolidating' => true]);
        $this->section = Office::create(['code' => '05012', 'name' => 'MIS SECTION', 'parent_id' => $this->division->id]);
        $this->section2 = Office::create(['code' => '05011', 'name' => 'PLANNING AND POLICY RESEARCH SECTION', 'parent_id' => $this->division->id]);
        $other = Office::create(['code' => '09000', 'name' => 'GAD']);

        $this->staff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section->id]);
        $this->staff2 = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section2->id]);
        $this->oic = User::factory()->create(['is_activated' => 1, 'office_id' => $this->division->id, 'fname' => 'Anne', 'minitial' => null, 'lname' => 'Dilay', 'designation' => 'OIC, Planning, Policy and Programming Division']);
        $this->outsider = User::factory()->create(['is_activated' => 1, 'office_id' => $other->id]);

        foreach ([$this->staff, $this->staff2, $this->head, $this->oic, $this->outsider] as $user) {
            $user->givePermissionTo('manage ppmp');
        }
    }

    protected function project(int $papId, array $overrides = []): array
    {
        return array_merge([
            'ppmp_pap_id'         => $papId,
            'description'         => 'Supply and Delivery of Hard Drive (4 TB)',
            'project_type'        => 'goods',
            'quantity'            => 8,
            'quantity_size'       => '3.5" HDD, SATA, 7200 RPM',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id'      => FundSource::where('code', 'COB')->value('id'),
            'allotment_class'     => 'mooe',
            'proc_start'          => '2027-02',
            'proc_end'            => '2027-04',
            'delivery_period'     => 'June 2027',
            'estimated_budget'    => '64,000.00',
            'supporting_documents' => 'Technical Specs, Market Scoping',
        ], $overrides);
    }

    /** A section PPMP with one PAP and one project, submitted. */
    protected function submittedSectionPpmp(Office $office, User $user, string $budget): Ppmp
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($office, 2027, $user);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'General Administration');
        $service->addItem($ppmp, array_merge($this->project($pap->id), ['proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => $budget]));
        $service->submit($ppmp, $user);

        return $ppmp->fresh();
    }

    public function test_section_builds_ppmp_with_paps_and_submits(): void
    {
        $response = $this->actingAs($this->staff)->postJson(route('procurement.ppmp.store'), [
            'office_id' => $this->section->id, 'fiscal_year' => 2027,
        ])->assertOk();
        $ppmp = Ppmp::where('uuid', $response->json('uuid'))->sole();
        $this->assertSame('05012-2027-V1', $ppmp->ppmp_no);

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('Add PAP')->assertSee('No PAPs yet');

        // PAP: code suggested as YY-office-NN, editable, unique per PPMP
        $this->get(route('procurement.ppmp.paps.entry', $ppmp))->assertOk()->assertSee('value="27-05012-01"', false);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-01', 'title' => 'ICT Infrastructure Management'])->assertOk();
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-01', 'title' => 'Duplicate'])->assertStatus(422);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '', 'title' => ''])->assertStatus(422)->assertJsonValidationErrors(['code', 'title']);
        $this->get(route('procurement.ppmp.paps.entry', $ppmp))->assertSee('value="27-05012-02"', false);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '25-05012-04', 'title' => 'Carried over PAP'])->assertOk();
        $pap = $ppmp->paps()->where('code', '27-05012-01')->first();
        $carried = $ppmp->paps()->where('code', '25-05012-04')->first();

        // Projects go under a PAP
        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'pap_id' => $pap->id]))->assertOk()->assertSee('27-05012-01 - ICT Infrastructure Management');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id))->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), array_diff_key($this->project($pap->id), ['ppmp_pap_id' => 1]))
            ->assertStatus(422)->assertJsonValidationErrors('ppmp_pap_id');
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, ['description' => 'Supply and Delivery of RAM', 'estimated_budget' => '30000']))->assertOk();
        $this->assertEquals('94000.00', $ppmp->fresh()->total_budget);

        // A PAP with projects cannot be removed; an empty one can
        $this->deleteJson(route('procurement.ppmp.paps.destroy', $ppmp), ['id' => $pap->id])->assertStatus(422);
        $this->deleteJson(route('procurement.ppmp.paps.destroy', $ppmp), ['id' => $carried->id])->assertOk();

        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()
            ->assertSee('PAP CODE: 27-05012-01 - ICT Infrastructure Management')
            ->assertSee('QTY: 8')
            ->assertSee('Supply and Delivery of RAM');

        $this->postJson(route('procurement.ppmp.submit', $ppmp))->assertOk();
        $this->assertSame(PpmpStatus::Submitted, $ppmp->fresh()->status);
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => '27-05012-09', 'title' => 'Late'])->assertStatus(422);
    }

    public function test_division_head_returns_then_approves_as_ppmp_no_1_and_amendment_as_no_2(): void
    {
        $mis = $this->submittedSectionPpmp($this->section, $this->staff, '64000');
        $research = app(PpmpService::class)->create($this->section2, 2027, $this->staff2);   // still a draft

        // Head returns from the section page with a reason
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $mis))->assertOk()
            ->assertSee('Approve in Division PPMP')->assertSee('id="btn_return"', false);
        $this->postJson(route('procurement.ppmp.return', $mis), ['remarks' => 'Add the brand-neutral specs'])->assertOk();
        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.submit', $mis))->assertOk();

        // Division page: section statuses, preview, approve (only the head)
        $this->actingAs($this->head)->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()
            ->assertSee('PLANNING, POLICY AND PROGRAMMING DIVISION')
            ->assertSee('05012-2027-V1')
            ->assertSee('Approve 1 submitted')
            ->assertSee('Not yet submitted (will not be included): PLANNING AND POLICY RESEARCH SECTION');
        $this->get(route('procurement.division-ppmp.preview', [$this->division, 'fy' => 2027]))->assertOk()
            ->assertSee('PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) NO. 1')->assertSee('FOR APPROVAL');

        $this->actingAs($this->staff)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'region' => 'lm', 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertStatus(422);

        $response = $this->actingAs($this->head)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'region' => 'lm', 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();
        $no1 = DivisionPpmp::sole();
        $this->assertSame(route('procurement.division-ppmp.show', $no1), $response->json('url'));
        $this->assertSame(1, $no1->ppmp_number);
        $this->assertEquals('64000.00', $no1->total_budget);
        $this->assertSame(PpmpStatus::Approved, $mis->fresh()->status);
        $this->assertSame(PpmpStatus::Draft, $research->fresh()->status);

        // Official print: matches the SRA form
        $this->get(route('procurement.division-ppmp.print', $no1))->assertOk()
            ->assertSee('PROJECT PROCUREMENT MANAGEMENT PLAN (PPMP) NO. 1')
            ->assertSee('Sugar Center Building, North Avenue, Diliman, Quezon City')
            ->assertSee('PAP CODE: 27-05012-01 - General Administration')
            ->assertSee('February 2027')
            ->assertSee('Corporate Operating Budget (COB)')
            ->assertSee('₱64,000.00')
            ->assertSee('TOTAL BUDGET:')
            ->assertSee('Anne Dilay')
            ->assertSee('Digna D. Gonzales')
            ->assertSee('[Head of the End-User or Implementing Unit]')
            ->assertDontSee('class="watermark"', false);

        // The research section submits, MIS amends -> next approval is PPMP No. 2 with both
        $service = app(PpmpService::class);
        $pap = $service->addPap($research, '27-05011-01', 'Preparation & Review of SRA Plans, Programs, and Policies');
        $service->addItem($research, array_merge($this->project($pap->id), ['proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => '26000']));
        $service->submit($research, $this->staff2);

        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.amend', $mis))->assertOk();
        $misV2 = Ppmp::where('amended_from_id', $mis->id)->sole();
        $this->assertSame('05012-2027-V2', $misV2->ppmp_no);
        $this->assertSame(['27-05012-01'], $misV2->paps()->pluck('code')->all());
        $service->updateItem($misV2->items()->first(), ['estimated_budget' => '80000']);
        $service->submit($misV2->fresh(), $this->staff);

        $this->actingAs($this->head)->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'region' => 'lm', 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();

        $no2 = DivisionPpmp::where('ppmp_number', 2)->sole();
        $this->assertSame(DivisionPpmp::STATUS_SUPERSEDED, $no1->fresh()->status);
        $this->assertEquals('106000.00', $no2->total_budget);
        $this->assertEqualsCanonicalizing([$misV2->id, $research->id], $no2->ppmps()->pluck('ppmps.id')->all());
        $this->assertSame(PpmpStatus::Superseded, $mis->fresh()->status);

        $this->get(route('procurement.division-ppmp.show', $no2))->assertOk()->assertSee('PPMP No. 2')->assertSee('05011-2027-V1');
        $this->get(route('procurement.division-ppmp.print', $no1))->assertOk()->assertSee('SUPERSEDED');
        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $misV2))->assertSee('PPMP No. 2');
    }

    public function test_division_page_groups_approvers_under_their_department_and_lists_every_unit(): void
    {
        // PPSPD (department) > Office of the Manager, PPPD (approves its sections), SPPDEM
        $ppspd = Office::create(['code' => null, 'acronym' => 'PPSPD', 'name' => 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'type' => 'department', 'budget_fund' => 'regular', 'head_user_id' => $this->head->id, 'is_consolidating' => true]);
        $this->division->update(['parent_id' => $ppspd->id, 'type' => 'division', 'acronym' => 'PPPD']);
        Office::create(['code' => '05000', 'acronym' => 'PPSPD-OM', 'name' => 'OFFICE OF THE MANAGER', 'type' => 'division', 'parent_id' => $ppspd->id]);
        Office::create(['code' => '05020', 'acronym' => 'SPPDEM', 'name' => 'SPECIAL PROJECTS DIVISION', 'type' => 'division', 'parent_id' => $ppspd->id]);
        app(PpmpService::class)->create($this->section2, 2027, $this->staff2);

        $this->actingAs($this->head)->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()
            ->assertSeeInOrder([
                'Department', 'PPSPD', 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT',
                '05000', 'OFFICE OF THE MANAGER', 'No PPMP for FY 2027 yet', '05020', 'SPECIAL PROJECTS DIVISION',
                'PLANNING, POLICY AND PROGRAMMING DIVISION', '05011', '05011-2027-V1', '05012', 'MIS SECTION', 'No PPMP for FY 2027 yet',
            ]);
    }

    public function test_market_scoping_checklist_and_attachments_on_a_project(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');
        $this->actingAs($this->staff);

        $parameters = collect(config('market_scoping.parameters'))->map(fn ($l, $k) => ['answer' => $k === 'storage' ? 'na' : 'yes', 'recommendation' => "ok {$k}"])->all();

        // Project with the checklist and a market survey PDF, sent as multipart form data
        $this->post(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, [
            'market_scoping' => ['period_from' => '2026-08', 'period_to' => '2026-09', 'activities' => ['consultations', 'price_sourcing', 'philgeps'], 'parameters' => $parameters],
            'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('canvass.pdf', 120, 'application/pdf')],
            'attachment_kinds' => ['market_survey'],
        ]), ['Accept' => 'application/json'])->assertOk();

        $item = $ppmp->items()->sole();
        $this->assertTrue($item->marketScopingComplete());
        $attachment = $item->attachments()->sole();
        $this->assertSame(['market_survey', 'canvass.pdf'], [$attachment->kind, $attachment->original_name]);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment->path);

        // Shown on the page, downloadable, printable checklist
        $this->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertSee('Market Scoping Checklist: complete')->assertSee('canvass.pdf');
        $this->get(route('procurement.ppmp.attachments.show', [$ppmp, $attachment]))->assertOk();
        $this->get(route('procurement.ppmp.items.market-scoping', [$ppmp, $item]))->assertOk()
            ->assertSee('MARKET SCOPING CHECKLIST')->assertSee('From 08/2026 To 09/2026')->assertSee('ok cost')->assertSee('canvass.pdf')
            ->assertSee('Use of data from PhilGEPS or agency websites')->assertSee('f. Identified Risk/s')->assertSee('Approved by:');
        $this->get(route('procurement.ppmp.print', $ppmp))->assertOk()->assertSee('Market Scoping Checklist')->assertSee('Market survey / price quotations');

        // The checklist on its own, from the project's menu: open, change, save
        $this->get(route('procurement.ppmp.items.market-scoping.entry', [$ppmp, $item]))->assertOk()->assertSee('MARKET_SCOPING_MODAL', false)->assertSee('ok cost');
        $this->postJson(route('procurement.ppmp.items.market-scoping.store', [$ppmp, $item]), ['market_scoping' => ['period_from' => '2026-09', 'period_to' => '2026-08']])
            ->assertStatus(422)->assertJsonValidationErrors('market_scoping.period_to');
        $this->postJson(route('procurement.ppmp.items.market-scoping.store', [$ppmp, $item]), ['market_scoping' => [
            'period_from' => '2026-07', 'period_to' => '2026-09', 'activities' => ['consultations'], 'parameters' => $parameters,
        ]])->assertOk();
        $this->assertSame('2026-07', $item->fresh()->market_scoping['period_from']);
        $this->assertSame('canvass.pdf', $item->fresh()->attachments()->sole()->original_name);   // project untouched
        $this->get(route('procurement.ppmp.items.market-scoping', [$ppmp, $item]))->assertSee('From 07/2026 To 09/2026');

        // Print all: every project's checklist, one sheet each (second project has none filled yet)
        $service->addItem($ppmp, array_merge($this->project($pap->id), ['description' => 'Supply of UPS', 'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => '20000']));
        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('Print Checklists');
        $all = $this->get(route('procurement.ppmp.market-scoping', $ppmp))->assertOk()
            ->assertSee('Market Scoping Checklists (2 projects)')->assertSeeInOrder(['Supply and Delivery of Hard Drive', 'ok cost', 'Supply of UPS', 'From __________ To __________']);
        $this->assertSame(2, substr_count($all->getContent(), 'class="sheet"'));
        $ppmp->items()->where('description', 'Supply of UPS')->delete();

        // Wrong file type and a period ending before it starts are refused
        $this->post(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, [
            'id' => $item->id,
            'market_scoping' => ['period_from' => '2026-09', 'period_to' => '2026-08'],
            'attachments' => [\Illuminate\Http\UploadedFile::fake()->create('virus.exe', 10)],
        ]), ['Accept' => 'application/json'])->assertStatus(422)->assertJsonValidationErrors(['attachments.0', 'market_scoping.period_to']);

        // Outsiders cannot download
        $this->actingAs($this->outsider)->get(route('procurement.ppmp.attachments.show', [$ppmp, $attachment]))->assertForbidden();

        // Approve, amend: the amendment has its own row for the same file; removing it there keeps the file for V1
        $service->submit($ppmp->fresh(), $this->staff);
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->division, 2027, \App\Enums\Region::Lm, $this->head, $this->head);
        $this->actingAs($this->staff)->deleteJson(route('procurement.ppmp.attachments.destroy', $ppmp), ['id' => $attachment->id])->assertStatus(422);   // approved: locked

        $v2 = $service->amend($ppmp->fresh(), $this->staff);
        $copy = $v2->items()->sole()->attachments()->sole();
        $this->assertSame($attachment->path, $copy->path);
        $this->assertTrue($v2->items()->sole()->marketScopingComplete());

        $this->deleteJson(route('procurement.ppmp.attachments.destroy', $v2), ['id' => $copy->id])->assertOk();
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($attachment->path);   // still used by V1

        // Deleting the unsubmitted amendment then V1's own row would remove the file; V1's row stays
        $this->assertSame(1, \App\Models\Procurement\PpmpItemAttachment::where('path', $attachment->path)->count());
    }

    public function test_removing_a_project_or_deleting_a_draft_removes_its_unused_files(): void
    {
        \Illuminate\Support\Facades\Storage::fake('local');
        $service = app(PpmpService::class);
        $files = app(\App\Services\Procurement\PpmpAttachmentService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);
        $pap = $service->addPap($ppmp, $service->suggestPapCode($ppmp), 'ICT');
        $line = array_merge($this->project($pap->id), ['proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => '1000']);

        $a = $service->addItem($ppmp, $line);
        $b = $service->addItem($ppmp, $line);
        $files->store($a, [\Illuminate\Http\UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['market_survey'], $this->staff);
        $files->store($b, [\Illuminate\Http\UploadedFile::fake()->create('b.pdf', 10, 'application/pdf')], ['specs'], $this->staff);
        [$pathA, $pathB] = [$a->attachments()->value('path'), $b->attachments()->value('path')];

        $service->removeItem($a->fresh());
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($pathA);
        \Illuminate\Support\Facades\Storage::disk('local')->assertExists($pathB);

        $service->deleteDraft($ppmp->fresh());
        \Illuminate\Support\Facades\Storage::disk('local')->assertMissing($pathB);
    }

    public function test_other_offices_cannot_view_or_change(): void
    {
        $ppmp = $this->submittedSectionPpmp($this->section, $this->staff, '64000');
        $pap = $ppmp->paps()->first();

        $this->actingAs($this->outsider)->get(route('procurement.ppmp.show', $ppmp))->assertForbidden();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id))->assertForbidden();
        $this->postJson(route('procurement.ppmp.paps.store', $ppmp), ['code' => 'x', 'title' => 'y'])->assertForbidden();
        $this->get(route('procurement.ppmp.print', $ppmp))->assertForbidden();

        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->division, 2027, \App\Enums\Region::Lm, $this->head, $this->oic);
        $this->get(route('procurement.division-ppmp.show', DivisionPpmp::sole()))->assertForbidden();
        $this->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()->assertDontSee('05012-2027-V1');

        // Head views the section PPMP read-only
        $this->actingAs($this->head)->get(route('procurement.ppmp.show', $ppmp))->assertOk()->assertDontSee('Add PAP');
    }

    public function test_delete_draft_needs_permission_and_frees_the_number(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $ppmp))->assertDontSee('id="btn_delete_ppmp"', false);
        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertForbidden();

        \Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'menu.ppmp-destroy', 'guard_name' => 'web']);
        $this->staff->givePermissionTo('menu.ppmp-destroy');

        $this->deleteJson(route('procurement.ppmp.destroy'), ['id' => $ppmp->uuid])->assertOk();
        $this->assertSame(0, Ppmp::withTrashed()->count());
        $this->assertSame('05012-2027-V1', $service->create($this->section, 2027, $this->staff)->ppmp_no);
    }

    public function test_section_copy_print(): void
    {
        $ppmp = $this->submittedSectionPpmp($this->section, $this->staff, '64000');

        $this->actingAs($this->staff)->get(route('procurement.ppmp.print', $ppmp))->assertOk()
            ->assertSee('PAP CODE: 27-05012-01 - General Administration')
            ->assertSee('Specs: 3.5&quot; HDD, SATA, 7200 RPM', false)
            ->assertSee('class="watermark">DRAFT', false)
            ->assertDontSee('(PPMP) NO.');
    }

    public function test_unit_price_times_quantity_is_the_estimated_budget(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);
        $pap = $service->addPap($ppmp, '27-05012-01', 'ICT Infrastructure Management');

        // Typed budget is ignored when quantity and unit price are given
        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['quantity' => 8, 'unit_cost' => '8,000.00', 'estimated_budget' => '1']))->assertOk();
        $item = $ppmp->items()->sole();
        $this->assertEquals('8000.00', $item->unit_cost);
        $this->assertEquals('64000.00', $item->estimated_budget);

        // Changing the quantity recomputes it (centavos stay exact)
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['id' => $item->id, 'quantity' => 3, 'unit_cost' => '333.33']))->assertOk();
        $this->assertEquals('999.99', $item->fresh()->estimated_budget);

        // A lot without unit price keeps the typed budget; budget is required then
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['description' => 'Consultancy', 'quantity' => null, 'unit_cost' => '', 'estimated_budget' => '2,500,000']))->assertOk();
        $this->assertEquals('2500000.00', $ppmp->items()->where('description', 'Consultancy')->value('estimated_budget'));
        $this->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['quantity' => 2, 'unit_cost' => '', 'estimated_budget' => '']))->assertStatus(422)->assertJsonValidationErrors('estimated_budget');

        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'id' => $item->id]))->assertOk()->assertSee('value="333.33"', false);
        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('@ ₱333.33');

        // Amendments keep the unit price
        $service->submit($ppmp->fresh(), $this->staff);
        app(\App\Services\Procurement\DivisionPpmpService::class)->approve($this->division, 2027, \App\Enums\Region::Lm, $this->head, $this->oic);
        $copy = $service->amend($ppmp->fresh(), $this->staff);
        $this->assertEquals('333.33', $copy->items()->where('description', '!=', 'Consultancy')->value('unit_cost'));
    }

    public function test_allotment_class_is_required_and_shown(): void
    {
        $service = app(PpmpService::class);
        $ppmp = $service->create($this->section, 2027, $this->staff);
        $pap = $service->addPap($ppmp, '27-05012-02', 'ICT Infrastructure Management');

        $this->actingAs($this->staff)->postJson(route('procurement.ppmp.items.store', $ppmp),
            $this->project($pap->id, ['allotment_class' => 'xx']))->assertStatus(422)->assertJsonValidationErrors('allotment_class');

        // MOOE and CO projects under the same PAP code; amounts with commas are accepted
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, ['allotment_class' => 'mooe', 'quantity' => null, 'estimated_budget' => '1,250,000.50']))->assertOk();
        $this->postJson(route('procurement.ppmp.items.store', $ppmp), $this->project($pap->id, ['allotment_class' => 'co', 'description' => 'Computer Desktop with UPS', 'quantity' => 34, 'unit_cost' => '90,000.00']))->assertOk();

        $this->assertEquals('1250000.50', $ppmp->items()->where('allotment_class', 'mooe')->value('estimated_budget'));
        $this->assertEquals('3060000.00', $ppmp->items()->where('allotment_class', 'co')->value('estimated_budget'));
        $this->get(route('procurement.ppmp.items.entry', [$ppmp, 'id' => $ppmp->items()->where('allotment_class', 'co')->value('id')]))
            ->assertSee('value="90,000.00"', false)->assertSee('value="3,060,000.00"', false)->assertSee('<option value="co" selected', false);
        $this->get(route('procurement.ppmp.show', $ppmp))->assertSee('>CO</span>', false);
    }

    public function test_lm_and_visayas_ppmps_are_separate_down_to_the_division_ppmp(): void
    {
        $visStaff = User::factory()->create(['is_activated' => 1, 'office_id' => $this->section->id, 'region' => 'vis']);
        $visStaff->givePermissionTo('manage ppmp');

        $lm = $this->submittedSectionPpmp($this->section, $this->staff, '64000');       // staff defaults to LM
        $vis = $this->submittedSectionPpmp($this->section, $visStaff, '10000');

        $this->assertSame(\App\Enums\Region::Vis, $vis->region);
        $this->assertSame('05012-2027-VIS-V1', $vis->ppmp_no);
        $this->assertSame('05012-2027-V1', $lm->ppmp_no);

        // A second Visayas PPMP for the same office and year is refused
        $this->expectsRefusal(fn () => app(PpmpService::class)->create($this->section, 2027, $visStaff));

        // Division page shows one card per region; approving Visayas leaves LM pending
        $this->actingAs($this->head)->get(route('procurement.division-ppmp.index', ['fy' => 2027]))->assertOk()
            ->assertSee('Luzon/Mindanao')->assertSee('Visayas')->assertSee('05012-2027-VIS-V1');

        $this->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'region' => 'vis', 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();

        $visNo1 = DivisionPpmp::where('region', 'vis')->sole();
        $this->assertSame(1, $visNo1->ppmp_number);
        $this->assertEquals('10000.00', $visNo1->total_budget);
        $this->assertSame(PpmpStatus::Submitted, $lm->fresh()->status);

        $this->postJson(route('procurement.division-ppmp.approve'), [
            'office_id' => $this->division->id, 'fiscal_year' => 2027, 'region' => 'lm', 'type' => 'final', 'prepared_by_id' => $this->oic->id,
        ])->assertOk();
        $this->assertSame(1, DivisionPpmp::where('region', 'lm')->sole()->ppmp_number);   // each region has its own No. 1

        $this->get(route('procurement.division-ppmp.print', $visNo1))->assertSee('PLANNING, POLICY AND PROGRAMMING DIVISION - VISAYAS');
    }

    protected function expectsRefusal(callable $action): void
    {
        try {
            $action();
            $this->fail('Expected a ProcurementException.');
        } catch (\App\Exceptions\ProcurementException) {
            $this->addToAssertionCount(1);
        }
    }

    public function test_history_is_in_a_modal_and_deactivated_fund_source_stays_on_its_project(): void
    {
        $ppmp = $this->submittedSectionPpmp($this->section, $this->staff, '64000');

        $this->actingAs($this->staff)->get(route('procurement.ppmp.show', $ppmp))->assertOk()
            ->assertSee('data-bs-target="#PPMP_HISTORY_MODAL"', false)
            ->assertSee('id="PPMP_HISTORY_MODAL"', false);

        // COB deactivated later: still selected when the project is opened, not offered for new ones
        $returned = app(PpmpService::class);
        $this->actingAs($this->head)->postJson(route('procurement.ppmp.return', $ppmp), ['remarks' => 'fix'])->assertOk();
        FundSource::where('code', 'COB')->update(['is_active' => false]);
        $item = $ppmp->items()->first();

        $this->actingAs($this->staff)->get(route('procurement.ppmp.items.entry', [$ppmp, 'id' => $item->id]))
            ->assertSee('selected>Corporate Operating Budget</option>', false);
        $this->get(route('procurement.ppmp.items.entry', $ppmp))->assertDontSee('Corporate Operating Budget</option>', false);
    }
}
