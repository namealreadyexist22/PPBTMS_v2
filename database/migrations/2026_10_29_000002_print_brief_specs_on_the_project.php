<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Whether the PPMP prints a project's brief specs is the office's choice on the project (default
     * yes, as before), not the standard item's; the TWG's full specifications are not printed on the PPMP.
     */
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('print_specs');
        });
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->boolean('print_brief_specs')->default(true)->after('quantity_size');
        });
        DB::table('ppmp_items')->update(['print_brief_specs' => true]);

        Schema::table('items', function (Blueprint $table) {
            $table->dropColumn('print_specs_on_ppmp');
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->boolean('print_specs_on_ppmp')->default(false)->after('specifications');
        });
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('print_brief_specs');
        });
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->boolean('print_specs')->default(false)->after('specifications');
        });
    }
};
