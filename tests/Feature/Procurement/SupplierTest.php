<?php

namespace Tests\Feature\Procurement;

use App\Models\Procurement\Supplier;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SupplierTest extends TestCase
{
    use RefreshDatabase, \Tests\Concerns\ReadsDataTables;

    protected User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->seed(RolePermissionSeeder::class);
        $this->user = User::factory()->create(['is_activated' => 1]);
        $this->user->givePermissionTo(Supplier::MANAGE_PERMISSION);
    }

    /** One page of the Suppliers table (search box = q, status filter), by name. */
    protected function rows(array $params = []): \Illuminate\Support\Collection
    {
        return $this->tableRows('procurement.suppliers.index', \App\DataTables\Procurement\SuppliersDataTable::class, $params + ['order' => [['column' => 0, 'dir' => 'asc']]]);
    }

    public function test_suppliers_are_added_edited_and_searched(): void
    {
        $this->actingAs($this->user)->get(route('procurement.suppliers.index'))->assertOk()->assertSee('tblSuppliers', false);
        $this->assertCount(0, $this->rows());
        $this->get(route('procurement.suppliers.entry'))->assertOk()->assertSee('Add Supplier');

        $this->postJson(route('procurement.suppliers.store'), ['name' => '  GPL Trading OPC ', 'address' => 'Imus City, Cavite', 'is_active' => 1])->assertOk();
        $supplier = Supplier::firstWhere('name', 'GPL Trading OPC');
        $this->assertSame('Imus City, Cavite', $supplier->address);

        $this->postJson(route('procurement.suppliers.store'), ['name' => 'GPL Trading OPC'])->assertStatus(422)->assertJsonPath('errors.name.0', 'This supplier is already on the list.');
        $this->postJson(route('procurement.suppliers.store'), ['id' => $supplier->id, 'name' => 'GPL Trading OPC', 'tin' => '123-456', 'is_active' => 0])->assertOk();
        $this->assertFalse($supplier->fresh()->is_active);
        $this->assertSame('123-456', $supplier->fresh()->tin);

        // Search (name, address or TIN) and status run in the database, one page at a time
        Supplier::create(['name' => 'Acme Office Supply', 'address' => 'Bacolod City', 'is_active' => true]);
        $this->assertSame(['Acme Office Supply', 'GPL Trading OPC'], $this->rows()->pluck('name')->all());
        $this->assertSame(['GPL Trading OPC'], $this->rows(['q' => 'imus'])->pluck('name')->all());
        $this->assertSame(['GPL Trading OPC'], $this->rows(['q' => '123-456'])->pluck('name')->all());
        $this->assertCount(0, $this->rows(['q' => 'nothing']));
        $this->assertSame(['Acme Office Supply'], $this->rows(['status' => 'active'])->pluck('name')->all());
        $this->assertSame(['GPL Trading OPC'], $this->rows(['status' => 'inactive'])->pluck('name')->all());
        $this->assertSame('—', $this->rows(['q' => 'acme'])->first()['tin']);

        $this->deleteJson(route('procurement.suppliers.destroy'), ['id' => $supplier->id])->assertOk();
        $this->assertNull($supplier->fresh());
    }

    public function test_the_page_needs_the_permission(): void
    {
        $other = User::factory()->create(['is_activated' => 1]);

        $this->actingAs($other)->get(route('procurement.suppliers.index'))->assertForbidden();
    }
}
