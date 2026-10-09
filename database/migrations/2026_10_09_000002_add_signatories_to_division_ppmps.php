<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Names printed on the Division PPMP. Empty = the "Prepared by" chosen at approval
        // and the division head (Submitted by), as before.
        Schema::table('division_ppmps', function (Blueprint $table) {
            $table->string('prepared_by_name')->nullable()->after('remarks');
            $table->string('prepared_by_designation')->nullable()->after('prepared_by_name');
            $table->string('submitted_by_name')->nullable()->after('prepared_by_designation');
            $table->string('submitted_by_designation')->nullable()->after('submitted_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('division_ppmps', fn (Blueprint $table) => $table->dropColumn(['prepared_by_name', 'prepared_by_designation', 'submitted_by_name', 'submitted_by_designation']));
    }
};
