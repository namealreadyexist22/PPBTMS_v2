<?php

namespace Tests\Feature\Procurement;

use App\Enums\Region;
use App\Models\User;
use App\Services\Procurement\AppService;
use App\Services\Procurement\OfficialsService;
use App\Services\Procurement\RfqService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

class OfficialsTest extends TestCase
{
    use RefreshDatabase;

    protected User $sec;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        Permission::findOrCreate('menu.app-manage');

        $this->sec = User::factory()->create(['is_activated' => 1, 'fname' => 'Bea', 'lname' => 'Sec']);
        $this->sec->givePermissionTo('manage officials');
    }

    public function test_the_page_sets_the_officials_used_by_the_app_and_rfqs(): void
    {
        [$hope, $chair, $vice, $m1, $m2, $supply] = User::factory()->count(6)->create(['is_activated' => 1]);

        $this->actingAs($this->sec)->get(route('procurement.officials.index'))->assertOk()
            ->assertSee('Head of the Procuring Entity (HOPE)')->assertSee('BAC Members')->assertSee('Luzon/Mindanao')->assertSee('View only');

        $this->postJson(route('procurement.officials.update'), [
            'region' => 'lm', 'hope' => $hope->id, 'bac_chair' => $chair->id, 'bac_vice_chair' => $vice->id,
            'bac_members' => [$m2->id, $m1->id], 'secretariat_head' => $this->sec->id, 'rfq_signatory' => $supply->id,
        ])->assertOk();

        $officials = app(OfficialsService::class);
        $this->assertSame([$m2->id, $m1->id], $officials->members(Region::Lm)->pluck('id')->all());   // in the order set
        $this->assertSame($vice->id, $officials->user(Region::Lm, 'bac_vice_chair')->id);

        // The APP's signatories are the same people
        $apps = app(AppService::class);
        $this->assertSame($hope->id, $apps->signatory(Region::Lm, 'approved')->id);
        $this->assertSame($chair->id, $apps->signatory(Region::Lm, 'recommended')->id);
        $this->assertSame($this->sec->id, $apps->signatory(Region::Lm, 'prepared')->id);

        // A new RFQ is signed by the RFQ signatory (still changeable on the RFQ)
        $rfq = app(RfqService::class)->create($this->sec, Region::Lm, []);
        $this->assertSame($supply->fresh()->fullname, $rfq->signatory_name);

        // Another region: view only for a user of LM
        $this->postJson(route('procurement.officials.update'), ['region' => 'vis', 'hope' => $hope->id])->assertForbidden();
    }

    public function test_access(): void
    {
        $nobody = User::factory()->create(['is_activated' => 1]);
        $this->actingAs($nobody)->get(route('procurement.officials.index'))->assertForbidden();

        // The BAC Secretariat (Prepare APP) can open it too
        $bac = User::factory()->create(['is_activated' => 1]);
        $bac->givePermissionTo('menu.app-manage');
        $this->actingAs($bac)->get(route('procurement.officials.index'))->assertOk();
    }
}
