<?php

namespace Tests\Feature\Procurement;

use App\Enums\AppStatus;
use App\Enums\AppType;
use App\Enums\Region;
use App\Exceptions\ProcurementException;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\AppService;
use App\Services\Procurement\DivisionPpmpService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppServiceTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected AppService $apps;
    protected User $head;
    protected User $bacSec;
    protected User $chair;
    protected User $hope;
    protected Office $division;
    protected Office $mis;
    protected Office $legal;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);
        $this->ppmps = app(PpmpService::class);
        $this->apps = app(AppService::class);

        $this->head = User::factory()->create();
        $this->division = Office::create(['code' => '05010', 'name' => 'PPPD', 'head_user_id' => $this->head->id, 'is_consolidating' => true]);
        $this->mis = Office::create(['code' => '05012', 'name' => 'MIS SECTION', 'parent_id' => $this->division->id]);
        $this->legal = Office::create(['code' => '04000', 'name' => 'LEGAL DEPARTMENT', 'head_user_id' => $this->head->id]);

        $this->bacSec = User::factory()->create(['fname' => 'Jenny', 'lname' => 'Flores', 'minitial' => null]);
        $this->chair = User::factory()->create();
        $this->hope = User::factory()->create();
        $this->apps->setSignatory(Region::Lm, 'prepared', $this->bacSec->id);
        $this->apps->setSignatory(Region::Lm, 'recommended', $this->chair->id);
        $this->apps->setSignatory(Region::Lm, 'approved', $this->hope->id);
    }

    /** An approved Division PPMP for the office with the given projects [description, budget, mode code]. */
    protected function approvedPpmp(Office $office, array $projects, Region $region = Region::Lm): Ppmp
    {
        $staff = User::factory()->create(['office_id' => $office->id, 'region' => $region]);
        $ppmp = $this->ppmps->create($office, 2027, $staff);
        $pap = $this->ppmps->addPap($ppmp, $this->ppmps->suggestPapCode($ppmp), 'General Administration');

        foreach ($projects as [$description, $budget, $mode]) {
            $this->ppmps->addItem($ppmp, [
                'ppmp_pap_id' => $pap->id, 'description' => $description, 'project_type' => 'goods',
                'procurement_mode_id' => ProcurementMode::where('code', $mode)->value('id'),
                'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
                'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => $budget,
            ]);
        }

        $this->ppmps->submit($ppmp, $staff);
        app(DivisionPpmpService::class)->approve($office->consolidatingOffice(), 2027, $region, $this->head, $this->head);

        return $ppmp->fresh();
    }

    public function test_bac_builds_groups_and_gets_the_app_approved(): void
    {
        $this->approvedPpmp($this->mis, [['Office supplies', '10000', 'SVP'], ['Laptops', '90000', 'SVP'], ['Various CSE at PS-DBM', '5000', 'PS']]);
        $this->approvedPpmp($this->legal, [['Office supplies', '4000', 'SVP']]);
        $this->approvedPpmp($this->mis->fresh(), [['Visayas tablets', '7000', 'SVP']], Region::Vis);   // not part of the LM APP

        $app = $this->apps->create(2027, Region::Lm, $this->bacSec);
        $this->assertCount(4, $this->apps->pool($app));

        $this->assertSame(4, $this->apps->generateLines($app));
        $this->assertCount(0, $this->apps->pool($app));
        $this->assertEquals('109000.00', $app->fresh()->total_budget);

        // CSE from PS-DBM goes to the CSE section without PAP
        $cse = $app->items()->where('is_cse', true)->sole();
        $this->assertNull($cse->pap_code);
        $this->assertSame('N/A', $cse->bid_criteria);

        // Group both offices' office supplies into one line
        $supplies = $app->items()->where('project_title', 'Office supplies')->pluck('id')->all();
        $line = $this->apps->group($app, $supplies);
        $this->assertEquals('14000.00', $line->estimated_budget);
        $this->assertSame('MIS SECTION; LEGAL DEPARTMENT', $line->end_user);
        $this->assertSame(3, $app->items()->count());
        $this->assertEquals('109000.00', $app->fresh()->total_budget);

        // Ungroup and group again
        $this->apps->ungroup($line);
        $this->assertSame(4, $app->items()->count());
        $line = $this->apps->group($app, $app->items()->where('project_title', 'Office supplies')->pluck('id')->all());

        // Workflow: Secretariat submits -> Chair recommends -> HOPE approves
        try {
            $this->apps->recommend($app, $this->chair);
            $this->fail('Must be submitted first.');
        } catch (ProcurementException) {
        }

        $this->apps->submit($app->fresh(), User::factory()->create());
        $this->assertSame('Jenny Flores', $app->latestSignatory('prepared')->name_snapshot);   // configured head, not the clicker

        try {
            $this->apps->recommend($app->fresh(), $this->hope);
            $this->fail('Only the BAC Chair recommends.');
        } catch (ProcurementException) {
        }

        $this->apps->returnToSecretariat($app->fresh(), $this->chair, 'Group the laptops with ICT');
        $this->assertSame(AppStatus::Draft, $app->fresh()->status);
        $this->apps->submit($app->fresh(), $this->bacSec);
        $this->apps->recommend($app->fresh(), $this->chair);
        $this->apps->approve($app->fresh(), $this->hope);
        $this->assertSame(AppStatus::Approved, $app->fresh()->status);

        try {
            $this->apps->generateLines($app->fresh());
            $this->fail('Approved APP is locked.');
        } catch (ProcurementException) {
        }
    }

    public function test_updated_version_follows_ppmp_amendments(): void
    {
        $ppmp = $this->approvedPpmp($this->mis, [['Office supplies', '10000', 'SVP'], ['Laptops', '90000', 'SVP']]);

        $app = $this->apps->create(2027, Region::Lm, $this->bacSec);
        $this->apps->generateLines($app);
        $this->apps->submit($app, $this->bacSec);
        $this->apps->recommend($app->fresh(), $this->chair);
        $this->apps->approve($app->fresh(), $this->hope);

        // MIS amends: laptops up to 120,000, office supplies removed, new project added -> PPMP No. 2
        $staff = User::factory()->create(['office_id' => $this->mis->id]);
        $v2 = $this->ppmps->amend($ppmp, $staff);
        $this->ppmps->updateItem($v2->items()->where('description', 'Laptops')->first(), ['estimated_budget' => '120000']);
        $this->ppmps->removeItem($v2->items()->where('description', 'Office supplies')->first());
        $this->ppmps->addItem($v2, ['ppmp_pap_id' => $v2->paps()->first()->id, 'description' => 'Printer', 'project_type' => 'goods',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'), 'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'proc_start' => '2027-05-01', 'proc_end' => '2027-06-01', 'estimated_budget' => '15000']);
        $this->ppmps->submit($v2->fresh(), $staff);
        app(DivisionPpmpService::class)->approve($this->division, 2027, Region::Lm, $this->head, $this->head);

        $updated = $this->apps->createUpdatedVersion($app->fresh(), $this->bacSec);
        $this->assertSame(2, $updated->version);
        $this->assertSame(AppType::Updated, $updated->type);
        $this->assertSame(['Laptops'], $updated->items()->pluck('project_title')->all());   // removed project dropped
        $this->assertEquals('120000.00', $updated->items()->sole()->estimated_budget);       // follows the amendment
        $this->assertSame(['Printer'], $this->apps->pool($updated)->pluck('description')->all());

        $this->apps->generateLines($updated);
        $this->apps->submit($updated->fresh(), $this->bacSec);
        $this->apps->recommend($updated->fresh(), $this->chair);
        $this->apps->approve($updated->fresh(), $this->hope);

        $this->assertSame(AppStatus::Superseded, $app->fresh()->status);
        $this->assertEquals('135000.00', $updated->fresh()->total_budget);
    }

    public function test_one_app_per_year_and_region_and_manage_gate(): void
    {
        $this->apps->create(2027, Region::Lm, $this->bacSec);
        $this->apps->create(2027, Region::Vis, $this->bacSec);

        try {
            $this->apps->create(2027, Region::Lm, $this->bacSec);
            $this->fail('Duplicate APP.');
        } catch (ProcurementException) {
        }

        \Spatie\Permission\Models\Permission::create(['name' => AppService::MANAGE_PERMISSION, 'guard_name' => 'web']);
        $lmSec = User::factory()->create(['region' => 'lm']);
        $lmSec->givePermissionTo(AppService::MANAGE_PERMISSION);

        $this->assertTrue($this->apps->canManage($lmSec, Region::Lm));
        $this->assertFalse($this->apps->canManage($lmSec, Region::Vis));
        $this->assertFalse($this->apps->canManage(User::factory()->create(), Region::Lm));
    }

    public function test_sida_funded_projects_have_their_own_app(): void
    {
        FundSource::create(['code' => 'GAA2017-CA', 'name' => 'GAA 2017 - Continuing Appropriation', 'fund_group' => 'sida']);
        $ppmp = $this->approvedPpmp($this->mis, [['Office supplies', '10000', 'SVP']]);

        // A SIDA-funded project in the same PPMP (added through an amendment)
        $staff = User::factory()->create(['office_id' => $this->mis->id]);
        $v2 = $this->ppmps->amend($ppmp, $staff);
        $this->ppmps->addItem($v2, ['ppmp_pap_id' => $v2->paps()->first()->id, 'description' => 'Training Box', 'project_type' => 'goods',
            'procurement_mode_id' => ProcurementMode::where('code', 'DAS')->value('id'), 'fund_source_id' => FundSource::where('code', 'GAA2017-CA')->value('id'),
            'proc_start' => '2027-01-01', 'proc_end' => '2027-12-01', 'estimated_budget' => '2000']);
        $this->ppmps->submit($v2->fresh(), $staff);
        app(DivisionPpmpService::class)->approve($this->division, 2027, Region::Lm, $this->head, $this->head);

        $regular = $this->apps->create(2027, Region::Lm, $this->bacSec);
        $sida = $this->apps->create(2027, Region::Lm, $this->bacSec, AppType::Final, \App\Enums\FundGroup::Sida);

        $this->assertSame(['Office supplies'], $this->apps->pool($regular)->pluck('description')->all());
        $this->assertSame(['Training Box'], $this->apps->pool($sida)->pluck('description')->all());
        $this->assertStringContainsString('SIDA', $sida->title());

        try {
            $this->apps->create(2027, Region::Lm, $this->bacSec, AppType::Final, \App\Enums\FundGroup::Sida);
            $this->fail('One SIDA APP per year and region.');
        } catch (ProcurementException) {
            $this->addToAssertionCount(1);
        }
    }
}
