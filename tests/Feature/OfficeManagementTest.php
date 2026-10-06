<?php

namespace Tests\Feature;

use App\Models\Procurement\Office;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfficeManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RolePermissionSeeder::class);
        $admin = User::factory()->create(['is_activated' => 1]);
        $admin->assignRole('Super Admin');
        $this->actingAs($admin);
    }

    public function test_office_number_must_be_digits(): void
    {
        $this->postJson(route('core.offices.store'), ['code' => 'PPSPD', 'name' => 'X'])
            ->assertStatus(422)->assertJsonValidationErrors('code');
    }

    public function test_office_cannot_be_placed_under_its_own_sub_office(): void
    {
        $top = Office::create(['code' => '05000', 'name' => 'Manager III']);
        $division = Office::create(['code' => '05010', 'name' => 'Division', 'parent_id' => $top->id]);
        $section = Office::create(['code' => '05011', 'name' => 'Section', 'parent_id' => $division->id]);

        $this->postJson(route('core.offices.store'), ['id' => $top->id, 'code' => '05000', 'name' => 'Manager III', 'parent_id' => $section->id])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');

        // A section under a section (any depth) is fine
        $this->postJson(route('core.offices.store'), ['code' => '05013', 'name' => 'Unit', 'type' => 'section', 'parent_id' => $section->id, 'is_active' => 1])
            ->assertOk();
    }

    public function test_organization_page_shows_the_tree_and_units_need_a_parent(): void
    {
        $this->seed(\Database\Seeders\OfficeSeeder::class);

        $this->get(route('core.offices.index'))->assertOk()
            ->assertSeeInOrder(['PPSPD', 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'PPPD', 'PPRS', 'MIS', 'SPPDEM'])
            ->assertSee('COB budget')->assertSee('SIDA budget');

        // A division must sit under a department; a department may be top level
        $this->postJson(route('core.offices.store'), ['code' => '05030', 'name' => 'New Division', 'type' => 'division'])
            ->assertStatus(422)->assertJsonValidationErrors('parent_id');
        $this->postJson(route('core.offices.store'), ['code' => '16000', 'name' => 'New Department', 'type' => 'department', 'budget_fund' => 'sida', 'is_active' => 1])
            ->assertOk();
        $this->assertSame('sida', Office::where('code', '16000')->first()->budget_fund->value);

        // The add form comes prefilled for a unit under PPPD
        $pppd = Office::where('code', '05010')->first();
        $this->get(route('core.offices.entry', ['type' => 'section', 'parent_id' => $pppd->id]))->assertOk()
            ->assertSee('Add Section')->assertSee('value="' . $pppd->id . '" selected', false);
    }

    public function test_seeded_offices_form_the_org_tree(): void
    {
        $this->seed(\Database\Seeders\OfficeSeeder::class);

        $hrrs = Office::where('code', '06021')->first();
        $this->assertSame(['06020', '06010', '06000'], [$hrrs->parent->code, $hrrs->parent->parent->code, $hrrs->parent->parent->parent->code]);
        $this->assertNull(Office::where('code', '11000')->value('parent_id'));

        // Types and departments: MIS is a section of PPPD in PPSPD; AFD-LM is a department of its own under ODA-AF
        $mis = Office::where('code', '05012')->first();
        $this->assertSame(['section', 'division', 'department'], [$mis->type, $mis->parent->type, $mis->parent->parent->type]);
        $this->assertSame('PPSPD', $mis->department->acronym);
        $this->assertSame('AFD-LM', $hrrs->department->acronym);
        $this->assertSame('sida', Office::where('code', '11000')->first()->budget_fund->value);
    }
}
