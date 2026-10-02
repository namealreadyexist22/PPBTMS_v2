<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Unit price: when quantity and unit cost are both given, estimated_budget = quantity x unit_cost
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->decimal('unit_cost', 15, 2)->nullable()->after('unit_id');
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('unit_cost');
        });
    }
};
