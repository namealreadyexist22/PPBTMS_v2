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
        $this->postJson(route('core.offices.store'), ['code' => '05013', 'name' => 'Unit', 'parent_id' => $section->id, 'is_active' => 1])
            ->assertOk();
    }

    public function test_seeded_offices_form_the_org_tree(): void
    {
        $this->seed(\Database\Seeders\OfficeSeeder::class);

        $hrrs = Office::where('code', '06021')->first();
        $this->assertSame(['06020', '06010', '06000'], [$hrrs->parent->code, $hrrs->parent->parent->code, $hrrs->parent->parent->parent->code]);
        $this->assertNull(Office::where('code', '11000')->value('parent_id'));
    }
}
