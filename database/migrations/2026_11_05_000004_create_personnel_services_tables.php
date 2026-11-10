<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Personnel Services (salaries) of a department as its own setting, apart from the CO + MOOE
 * allocation the PPMPs use: set without an allocation, with its own history. Printed on the
 * COB Breakdown per Office / Department. Moves budget_allocations.ps_amount here.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('personnel_services', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('department_id')->constrained('offices');
            $table->string('fund_group', 10);
            $table->decimal('amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'department_id', 'fund_group']);
        });

        Schema::create('personnel_service_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('personnel_service_id')->constrained('personnel_services')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_amount', 15, 2)->nullable();
            $table->decimal('new_amount', 15, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });

        foreach (DB::table('budget_allocations')->where('ps_amount', '>', 0)->get() as $a) {
            $id = DB::table('personnel_services')->insertGetId([
                'fiscal_year' => $a->fiscal_year, 'department_id' => $a->department_id, 'fund_group' => $a->fund_group,
                'amount' => $a->ps_amount, 'created_by' => $a->updated_by, 'updated_by' => $a->updated_by,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('personnel_service_changes')->insert([
                'personnel_service_id' => $id, 'user_id' => $a->updated_by, 'old_amount' => null, 'new_amount' => $a->ps_amount,
                'reason' => null, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropColumn('ps_amount'));
    }

    public function down(): void
    {
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->decimal('ps_amount', 15, 2)->default(0)->after('amount');
        });

        foreach (DB::table('personnel_services')->get() as $ps) {
            DB::table('budget_allocations')->where('fiscal_year', $ps->fiscal_year)->where('department_id', $ps->department_id)
                ->where('fund_group', $ps->fund_group)->update(['ps_amount' => $ps->amount]);
        }

        Schema::dropIfExists('personnel_service_changes');
        Schema::dropIfExists('personnel_services');
    }
};
