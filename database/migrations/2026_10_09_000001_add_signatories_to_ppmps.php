<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Names printed on the section PPMP (and its Market Scoping Checklists). Empty = the
        // preparer (Prepared by) and the approving head (Submitted by), as before.
        Schema::table('ppmps', function (Blueprint $table) {
            $table->string('prepared_by_name')->nullable()->after('remarks');
            $table->string('prepared_by_designation')->nullable()->after('prepared_by_name');
            $table->string('submitted_by_name')->nullable()->after('prepared_by_designation');
            $table->string('submitted_by_designation')->nullable()->after('submitted_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('ppmps', fn (Blueprint $table) => $table->dropColumn(['prepared_by_name', 'prepared_by_designation', 'submitted_by_name', 'submitted_by_designation']));
    }
};
