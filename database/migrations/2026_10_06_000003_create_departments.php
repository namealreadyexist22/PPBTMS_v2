<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Departments as given by the Budget office: [code, name, budget fund, office numbers].
     * A department is a grouping, not an office: e.g. PPSPD's offices are 05000 (Manager III,
     * which prepares its own PPMP) up to 05020, and they all share PPSPD's budget.
     */
    public const DEPARTMENTS = [
        ['OSB', 'OFFICE OF THE SUGAR BOARD', 'regular', ['01000']],
        ['IAD', 'INTERNAL AUDIT DEPARTMENT', 'regular', ['02000', '02010', '02020']],
        ['OA', 'OFFICE OF THE ADMINISTRATOR', 'regular', ['03000']],
        ['LEGAL', 'LEGAL DEPARTMENT', 'regular', ['04000']],
        ['PPSPD', 'PLANNING, POLICY AND SPECIAL PROJECTS DEPARTMENT', 'regular', ['05000', '05010', '05011', '05012', '05020']],
        ['ODA-AF', 'OFC OF THE DEP. ADMIN - ADMINISTRATION AND FINANCE', 'regular', ['06000']],
        ['AFD-LM', 'AFD-LUZON MINDANAO', 'regular', ['06010', '06020', '06021', '06022', '06030', '06040']],
        ['AFD-VIS', 'AFD-VISAYAS', 'regular', ['06550', '06560', '06561', '06562', '06570', '06571', '06572']],
        ['ODA-RDE', 'OFC OF THE DEP. ADMIN - RESEARCH, DEVELOPMENT AND EXTENSION', 'regular', ['07000']],
        ['RDE-LM', 'RDE-LM', 'regular', ['07010', '07011', '07012', '07020', '07030', '07040', '07050']],
        ['RDE-VIS', 'RDE-VIS', 'regular', ['07560', '07570', '07580', '07581', '07582', '07583', '07590']],
        ['ODA-RD', 'OFC OF THE DEP. ADMIN - REGULATIONS', 'regular', ['08000']],
        ['RD-LM', 'RD-LM', 'regular', ['08010', '08020', '08021', '08022', '08030', '08040', '08050']],
        ['RD-VIS', 'RD-VIS', 'regular', ['08560', '08570', '08580', '08590']],
        ['GAD', 'GENDER AND DEVELOPMENT (GAD)', 'regular', ['09000']],
        ['SIDA-BFP', 'SIDA-BFP', 'sida', ['10000']],
        ['SIDA-SCP', 'SIDA-SCP', 'sida', ['11000']],
        ['SIDA-HRD', 'SIDA-HRD', 'sida', ['12000']],
        ['SIDA-FMR', 'SIDA-FMR', 'sida', ['13000']],
        ['SIDA-R&D', 'SIDA-R&D', 'sida', ['14000']],
        ['RBAC', 'REGIONAL BIDS AND AWARDS COMMITTEE', 'regular', ['15000']],
    ];

    public function up(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('fund_group', 10)->default('regular');   // budget fund: COB or SIDA
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('parent_id')->constrained('departments')->nullOnDelete();
        });

        $now = now();
        foreach (self::DEPARTMENTS as [$code, $name, $fund, $offices]) {
            $id = DB::table('departments')->insertGetId(['code' => $code, 'name' => $name, 'fund_group' => $fund, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now]);
            DB::table('offices')->whereIn('code', $offices)->update(['department_id' => $id]);
        }

        // Budgets now belong to departments. Allocations set on a department's head office move to
        // the department; ones left on other offices (ignored since budgets went per department) go.
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('fiscal_year')->constrained('departments')->cascadeOnDelete();
        });

        foreach (DB::table('budget_allocations')->get() as $allocation) {
            $office = DB::table('offices')->where('id', $allocation->office_id)->first();
            $departmentId = $office && $office->is_department ? $office->department_id : null;

            $departmentId
                ? DB::table('budget_allocations')->where('id', $allocation->id)->update(['department_id' => $departmentId])
                : DB::table('budget_allocations')->where('id', $allocation->id)->delete();
        }

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->unique(['fiscal_year', 'department_id', 'fund_group'], 'budget_alloc_dept_unique');
        });
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropForeign(['office_id']);
            $table->dropUnique(['fiscal_year', 'office_id', 'fund_group']);
        });
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropColumn('office_id');
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn(['is_department', 'budget_fund']);
        });
    }

    public function down(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->boolean('is_department')->default(false);
            $table->string('budget_fund', 10)->nullable();
        });

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->foreignId('office_id')->nullable()->constrained('offices');
        });
        DB::table('budget_allocations')->delete();
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->unique(['fiscal_year', 'office_id', 'fund_group']);
        });
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropUnique('budget_alloc_dept_unique');
        });
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropColumn('department_id'));

        Schema::table('offices', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
            $table->dropColumn('department_id');
        });
        Schema::dropIfExists('departments');
    }
};
