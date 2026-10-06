<?php

namespace Tests\Feature\Procurement;

use App\Enums\FundGroup;
use App\Enums\Region;
use App\Models\Procurement\Department;
use App\Models\Procurement\FundSource;
use App\Models\Procurement\Office;
use App\Models\Procurement\Ppmp;
use App\Models\Procurement\ProcurementMode;
use App\Models\User;
use App\Services\Procurement\AppService;
use App\Services\Procurement\BudgetAllocationService;
use App\Services\Procurement\DivisionPpmpService;
use App\Services\Procurement\PpmpService;
use Database\Seeders\ProcurementLookupSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Notifications\ChannelManager;
use RuntimeException;
use Tests\TestCase;

class ProcurementNotificationTest extends TestCase
{
    use RefreshDatabase;

    protected PpmpService $ppmps;
    protected User $head;
    protected User $staff;
    protected Office $division;
    protected Office $mis;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(ProcurementLookupSeeder::class);
        $this->ppmps = app(PpmpService::class);

        $this->head = User::factory()->create();
        $this->division = Office::create(['code' => '05010', 'name' => 'PPPD', 'acronym' => 'PPPD', 'head_user_id' => $this->head->id, 'is_consolidating' => true]);
        $this->mis = Office::create(['code' => '05012', 'name' => 'MIS SECTION', 'acronym' => 'MIS', 'parent_id' => $this->division->id]);
        $this->staff = User::factory()->create(['office_id' => $this->mis->id]);
    }

    protected function draft(): Ppmp
    {
        $ppmp = $this->ppmps->create($this->mis, 2027, $this->staff);
        $pap = $this->ppmps->addPap($ppmp, $this->ppmps->suggestPapCode($ppmp), 'ICT');
        $this->ppmps->addItem($ppmp, [
            'ppmp_pap_id' => $pap->id, 'description' => 'Laptops', 'project_type' => 'goods',
            'procurement_mode_id' => ProcurementMode::where('code', 'SVP')->value('id'),
            'fund_source_id' => FundSource::where('code', 'COB')->value('id'),
            'proc_start' => '2027-02-01', 'proc_end' => '2027-04-01', 'estimated_budget' => '500000',
        ]);

        return $ppmp->fresh();
    }

    protected function latest(User $user): ?array
    {
        return $user->fresh()->notifications()->latest()->first()?->data;
    }

    public function test_ppmp_submit_return_and_approve_notify_the_right_people(): void
    {
        $ppmp = $this->draft();

        // Submit -> the division head
        $this->ppmps->submit($ppmp, $this->staff);
        $this->assertSame('PPMP submitted for approval', $this->latest($this->head)['title']);
        $this->assertStringContainsString("{$ppmp->ppmp_no} from MIS", $this->latest($this->head)['message']);
        $this->assertSame(route('procurement.ppmp.show', $ppmp), $this->latest($this->head)['url']);
        $this->assertCount(0, $this->staff->fresh()->notifications);   // never the one who acted

        // Return -> the preparer, with the remarks
        $this->ppmps->returnToOffice($ppmp->fresh(), $this->head, 'Add specifications');
        $this->assertSame('PPMP returned', $this->latest($this->staff)['title']);
        $this->assertStringContainsString('Add specifications', $this->latest($this->staff)['message']);

        // Resubmit, approve -> the preparer hears it is in PPMP No. 1
        $this->travel(1)->minute();
        $this->ppmps->submit($ppmp->fresh(), $this->staff);
        app(DivisionPpmpService::class)->approve($this->division, 2027, Region::Lm, $this->head, $this->head);
        $this->assertSame('PPMP approved', $this->latest($this->staff)['title']);
        $this->assertStringContainsString('PPPD PPMP No. 1', $this->latest($this->staff)['message']);

        // Amendment submitted -> the head again
        $this->travel(1)->minute();
        $v2 = $this->ppmps->amend($ppmp->fresh(), $this->staff);
        $this->ppmps->submit($v2, $this->staff);
        $this->assertSame('Amendment submitted for approval', $this->latest($this->head)['title']);
    }

    public function test_app_workflow_notifies_chair_hope_and_secretariat(): void
    {
        $apps = app(AppService::class);
        [$sec, $chair, $hope] = [User::factory()->create(), User::factory()->create(), User::factory()->create()];
        $apps->setSignatory(Region::Lm, 'prepared', $sec->id);
        $apps->setSignatory(Region::Lm, 'recommended', $chair->id);
        $apps->setSignatory(Region::Lm, 'approved', $hope->id);

        $this->ppmps->submit($this->draft(), $this->staff);
        app(DivisionPpmpService::class)->approve($this->division, 2027, Region::Lm, $this->head, $this->head);

        $app = $apps->create(2027, Region::Lm, $sec);
        $apps->generateLines($app);

        $apps->submit($app, $sec);
        $this->assertSame('APP for your recommendation', $this->latest($chair)['title']);

        $apps->returnToSecretariat($app->fresh(), $chair, 'Group the laptops');
        $this->assertSame('APP returned', $this->latest($sec)['title']);
        $this->assertStringContainsString('Group the laptops', $this->latest($sec)['message']);

        $apps->submit($app->fresh(), $sec);
        $apps->recommend($app->fresh(), $chair);
        $this->assertSame('APP for your approval', $this->latest($hope)['title']);

        $this->travel(1)->minute();
        $apps->approve($app->fresh(), $hope);
        $this->assertSame('APP approved', $this->latest($sec)['title']);
        $this->assertSame(route('procurement.app.show', $app), $this->latest($sec)['url']);
    }

    public function test_budget_change_notifies_the_department_heads_but_not_the_first_allocation(): void
    {
        $budget = app(BudgetAllocationService::class);
        $officer = User::factory()->create();
        $dept = Department::where('code', 'PPSPD')->firstOrFail();
        $this->division->update(['department_id' => $dept->id]);
        $this->mis->update(['department_id' => $dept->id]);

        $budget->save($dept, 2027, FundGroup::Regular, '15000000', $officer);
        $this->assertCount(0, $this->head->fresh()->notifications);

        $budget->save($dept, 2027, FundGroup::Regular, '16000000', $officer, 'Realign from RDE-LM');
        $this->assertSame('PPSPD budget changed', $this->latest($this->head)['title']);
        $this->assertStringContainsString('₱15,000,000.00 → ₱16,000,000.00 (Realign from RDE-LM)', $this->latest($this->head)['message']);
    }

    public function test_a_failing_notification_never_breaks_the_action(): void
    {
        app(ChannelManager::class)->extend('database', fn () => new class {
            public function send($notifiable, $notification): void
            {
                throw new RuntimeException('channel down');
            }
        });

        \Illuminate\Support\Facades\Log::spy();

        $ppmp = $this->draft();
        $this->ppmps->submit($ppmp, $this->staff);

        $this->assertSame(\App\Enums\PpmpStatus::Submitted, $ppmp->fresh()->status);
        \Illuminate\Support\Facades\Log::shouldHaveReceived('warning')->withArgs(fn ($message) => str_contains($message, 'channel down'))->once();
    }
}
