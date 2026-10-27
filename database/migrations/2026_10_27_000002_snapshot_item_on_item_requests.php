<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The item as it was when requested (code, name, unit, standard cost, TWG specifications), so a
     * request keeps its history when the TWG later changes the catalog, as PPMP projects and PR lines do.
     */
    public function up(): void
    {
        Schema::table('item_requests', function (Blueprint $table) {
            $table->string('item_code', 30)->nullable()->after('item_id');
            $table->string('item_name')->nullable()->after('item_code');
            $table->string('unit_name', 50)->nullable()->after('item_name');
            $table->decimal('unit_cost', 15, 2)->nullable()->after('unit_name');
            $table->text('specifications')->nullable()->after('unit_cost');
        });

        // Requests made before: the catalog as it is now (the closest record there is)
        foreach (DB::table('item_requests')->get(['id', 'item_id']) as $request) {
            $item = DB::table('items')->leftJoin('units', 'units.id', '=', 'items.unit_id')->where('items.id', $request->item_id)
                ->first(['items.code', 'items.name', 'units.name as unit', 'items.standard_unit_cost', 'items.specifications']);
            if ($item) {
                DB::table('item_requests')->where('id', $request->id)->update([
                    'item_code' => $item->code, 'item_name' => $item->name, 'unit_name' => $item->unit,
                    'unit_cost' => $item->standard_unit_cost, 'specifications' => $item->specifications,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('item_requests', function (Blueprint $table) {
            $table->dropColumn(['item_code', 'item_name', 'unit_name', 'unit_cost', 'specifications']);
        });
    }
};
