<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** The SIDA departments are budgeted under SIDA; every other department under COB. */
    public const SIDA_DEPARTMENTS = ['10000', '11000', '12000', '13000', '14000'];

    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('budget_fund', 10)->nullable()->after('is_department');
        });

        DB::table('offices')->where('is_department', true)->update(['budget_fund' => 'regular']);
        DB::table('offices')->where('is_department', true)->whereIn('code', self::SIDA_DEPARTMENTS)->update(['budget_fund' => 'sida']);
    }

    public function down(): void
    {
        Schema::table('offices', fn (Blueprint $table) => $table->dropColumn('budget_fund'));
    }
};
