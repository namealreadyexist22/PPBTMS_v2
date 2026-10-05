<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // Luzon/Mindanao vs Visayas. An office like PPSPD has staff in both, so the region
    // is on the user; a PPMP takes its preparer's region and goes to that region's APP.
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region', 5)->default('lm')->after('office_id');
        });

        Schema::table('ppmps', function (Blueprint $table) {
            $table->string('region', 5)->default('lm')->after('office_id');
            // New index first: on MySQL the old one also backs the office_id foreign key
            $table->unique(['office_id', 'fiscal_year', 'region', 'version']);
        });

        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropUnique(['office_id', 'fiscal_year', 'version']);
        });

        Schema::table('division_ppmps', function (Blueprint $table) {
            $table->string('region', 5)->default('lm')->after('office_id');
            $table->unique(['office_id', 'fiscal_year', 'region', 'ppmp_number']);
        });

        Schema::table('division_ppmps', function (Blueprint $table) {
            $table->dropUnique(['office_id', 'fiscal_year', 'ppmp_number']);
        });
    }

    public function down(): void
    {
        Schema::table('division_ppmps', function (Blueprint $table) {
            $table->unique(['office_id', 'fiscal_year', 'ppmp_number']);
        });

        Schema::table('division_ppmps', function (Blueprint $table) {
            $table->dropUnique(['office_id', 'fiscal_year', 'region', 'ppmp_number']);
            $table->dropColumn('region');
        });

        Schema::table('ppmps', function (Blueprint $table) {
            $table->unique(['office_id', 'fiscal_year', 'version']);
        });

        Schema::table('ppmps', function (Blueprint $table) {
            $table->dropUnique(['office_id', 'fiscal_year', 'region', 'version']);
            $table->dropColumn('region');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
