<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // offices.code is the office number (e.g. 05000) used in PPMP numbers;
    // acronym is the short name shown in lists (e.g. PPSPD).
    // division/section text columns are dropped: parent_id already links a section to its division.
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('acronym')->nullable()->after('code');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['division', 'section']);
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('division')->nullable();
            $table->string('section')->nullable();
            $table->dropColumn('acronym');
        });
    }
};
