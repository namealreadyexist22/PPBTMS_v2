<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // "Canvassed by" printed on the RFQ (FM-AFD-PPS-015)
        Schema::table('rfqs', function (Blueprint $table) {
            $table->string('canvassed_by_name')->nullable()->after('signatory_designation');
        });
    }

    public function down(): void
    {
        Schema::table('rfqs', fn (Blueprint $table) => $table->dropColumn('canvassed_by_name'));
    }
};
