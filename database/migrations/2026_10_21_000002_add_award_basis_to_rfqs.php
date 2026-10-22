<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per item (each item to its lowest offer) or per lot (all items to the lowest lot total)
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('award_basis', 10)->default('item')->after('procurement_mode_id');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', fn (Blueprint $table) => $table->dropColumn('award_basis'));
    }
};
