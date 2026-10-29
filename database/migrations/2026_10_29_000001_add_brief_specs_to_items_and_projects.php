<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A standard item's brief specs (a project's "Size / brief specs" on the PPMP) apart from the
        // TWG's full specifications, and whether the PPMP prints the full ones too
        Schema::table('items', function (Blueprint $table) {
            $table->text('brief_specs')->nullable()->after('description');
            $table->boolean('print_specs_on_ppmp')->default(false)->after('specifications');
        });

        // A project keeps the TWG's full specifications as when it took the standard item (as PR lines
        // do), and whether its PPMP prints them
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->text('specifications')->nullable()->after('quantity_size');
            $table->boolean('print_specs')->default(false)->after('specifications');
        });

        // Projects on a standard item until now: their full specs are the catalog's (quantity_size held them)
        DB::table('ppmp_items')->whereNotNull('item_id')->orderBy('id')->each(function ($project) {
            $specs = DB::table('items')->where('id', $project->item_id)->value('specifications');
            DB::table('ppmp_items')->where('id', $project->id)->update(['specifications' => $specs]);
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', fn (Blueprint $table) => $table->dropColumn(['specifications', 'print_specs']));
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn(['brief_specs', 'print_specs_on_ppmp']));
    }
};
