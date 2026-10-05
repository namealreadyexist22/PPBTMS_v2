<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // SIDA has its own APP: each fund source says which APP (regular / sida) its projects go to,
    // and APPs are kept per fiscal year, region and fund group.
    public function up(): void
    {
        Schema::table('fund_sources', function (Blueprint $table) {
            $table->string('fund_group', 10)->default('regular')->after('name');
        });
        DB::table('fund_sources')->where('code', 'SIDA')->update(['fund_group' => 'sida']);

        Schema::table('annual_procurement_plans', function (Blueprint $table) {
            $table->string('fund_group', 10)->default('regular')->after('region');
            $table->unique(['fiscal_year', 'region', 'fund_group', 'version'], 'app_year_region_fund_version_unique');
        });

        Schema::table('annual_procurement_plans', function (Blueprint $table) {
            $table->dropUnique(['fiscal_year', 'region', 'version']);
        });
    }

    public function down(): void
    {
        Schema::table('annual_procurement_plans', function (Blueprint $table) {
            $table->unique(['fiscal_year', 'region', 'version']);
        });

        Schema::table('annual_procurement_plans', function (Blueprint $table) {
            $table->dropUnique('app_year_region_fund_version_unique');
            $table->dropColumn('fund_group');
        });

        Schema::table('fund_sources', function (Blueprint $table) {
            $table->dropColumn('fund_group');
        });
    }
};
