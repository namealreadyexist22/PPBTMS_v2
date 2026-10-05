<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // An allocation is one amount covering CO and MOOE together (no separate CO / MOOE caps)
    public function up(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->decimal('amount', 15, 2)->default(0)->after('fund_group');
        });
        DB::table('budget_allocations')->update(['amount' => DB::raw('mooe_amount + co_amount')]);
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropColumn(['mooe_amount', 'co_amount']);
        });

        Schema::table('budget_allocation_changes', function (Blueprint $table) {
            $table->decimal('old_amount', 15, 2)->nullable()->after('user_id');
            $table->decimal('new_amount', 15, 2)->default(0)->after('old_amount');
        });
        DB::table('budget_allocation_changes')->update([
            'old_amount' => DB::raw('CASE WHEN old_mooe IS NULL THEN NULL ELSE old_mooe + old_co END'),
            'new_amount' => DB::raw('new_mooe + new_co'),
        ]);
        Schema::table('budget_allocation_changes', function (Blueprint $table) {
            $table->dropColumn(['old_mooe', 'old_co', 'new_mooe', 'new_co']);
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->decimal('mooe_amount', 15, 2)->default(0);
            $table->decimal('co_amount', 15, 2)->default(0);
        });
        DB::table('budget_allocations')->update(['mooe_amount' => DB::raw('amount')]);
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropColumn('amount'));

        Schema::table('budget_allocation_changes', function (Blueprint $table) {
            $table->decimal('old_mooe', 15, 2)->nullable();
            $table->decimal('old_co', 15, 2)->nullable();
            $table->decimal('new_mooe', 15, 2)->default(0);
            $table->decimal('new_co', 15, 2)->default(0);
        });
        DB::table('budget_allocation_changes')->update([
            'old_mooe' => DB::raw('old_amount'), 'old_co' => DB::raw('CASE WHEN old_amount IS NULL THEN NULL ELSE 0 END'), 'new_mooe' => DB::raw('new_amount'),
        ]);
        Schema::table('budget_allocation_changes', fn (Blueprint $table) => $table->dropColumn(['old_amount', 'new_amount']));
    }
};
