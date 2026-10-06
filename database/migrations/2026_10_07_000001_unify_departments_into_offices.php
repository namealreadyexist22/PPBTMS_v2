<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One organization tree: Department > Division > Section. A department is the top unit of its
     * branch: it receives the budget (budget_fund = COB / SIDA) and can prepare its own PPMP
     * (e.g. the Manager III's PAPs). The separate departments list goes away.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('type', 20)->default('section')->after('code');   // department, division, section
            $table->string('budget_fund', 10)->nullable()->after('is_consolidating');
        });

        // Each old department becomes its top office (e.g. PPSPD -> 05000)
        $headOf = [];
        foreach (DB::table('departments')->get() as $department) {
            $offices = DB::table('offices')->where('department_id', $department->id)->orderBy('code')->get();
            $ids = $offices->pluck('id')->all();
            $head = $offices->first(fn ($o) => ! in_array($o->parent_id, $ids));

            if (! $head) {
                continue;
            }

            $headOf[$department->id] = $head->id;
            DB::table('offices')->where('id', $head->id)->update([
                'type'        => 'department',
                'budget_fund' => $department->fund_group,
                'acronym'     => $head->acronym ?: $department->code,
                'name'        => $department->name,
            ]);
        }

        // The rest by how far below their department they sit; top offices without one become departments
        $offices = DB::table('offices')->get()->keyBy('id');
        foreach ($offices as $office) {
            if ($office->type === 'department' || isset(array_flip($headOf)[$office->id])) {
                continue;
            }

            $steps = 0;
            for ($o = $office; $o && ! in_array($o->id, $headOf); $o = $o->parent_id ? $offices->get($o->parent_id) : null) {
                $steps++;
            }

            // $steps counts the office up to (not including) its department; with no department above,
            // up to and including the top office, which becomes the department
            $depth = $o ? $steps : $steps - 1;
            $type = match (true) { $depth <= 0 => 'department', $depth === 1 => 'division', default => 'section' };
            DB::table('offices')->where('id', $office->id)->update(['type' => $type] + ($type === 'department' ? ['budget_fund' => 'regular'] : []));
        }

        // PPSPD's units as the organization names them (only where still the seeded names)
        foreach ([
            '05010' => ['PPPD', 'PLANNING, POLICY AND PROGRAMMING DIVISION', 'PPSPD - PLANNING, POLICY AND PROGRAMMING DIVISION'],
            '05020' => ['SPPDEM', 'SPECIAL PROJECTS, PROJECT DEVELOPMENT, EVALUATION AND MONITORING DIVISION', 'PPSPD - SPECIAL PROJECTS, PROJECT DEVELOPMENT, EVALUATION AND MONITORING DIVISION'],
            '05011' => ['PPRS', 'PLANNING AND POLICY RESEARCH SECTION', 'PPSPD - PPRD - PLANNING AND POLICY RESEARCH SECTION'],
            '05012' => ['MIS', 'MIS SECTION', 'PPSPD - PPRD - MIS SECTION'],
        ] as $code => [$acronym, $name, $oldName]) {
            DB::table('offices')->where('code', $code)->where('name', $oldName)->update(['name' => $name]);
            DB::table('offices')->where('code', $code)->whereNull('acronym')->update(['acronym' => $acronym]);
        }

        // Budgets now point at the department unit (offices.id)
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
        foreach (DB::table('budget_allocations')->get() as $allocation) {
            isset($headOf[$allocation->department_id])
                ? DB::table('budget_allocations')->where('id', $allocation->id)->update(['department_id' => $headOf[$allocation->department_id]])
                : DB::table('budget_allocations')->where('id', $allocation->id)->delete();
        }
        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->foreign('department_id')->references('id')->on('offices')->cascadeOnDelete();
        });

        Schema::table('offices', function (Blueprint $table) {
            $table->dropForeign(['department_id']);
        });
        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('department_id');
        });
        Schema::dropIfExists('departments');
    }

    public function down(): void
    {
        Schema::create('departments', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('name');
            $table->string('fund_group', 10)->default('regular');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
        Schema::table('offices', function (Blueprint $table) {
            $table->foreignId('department_id')->nullable()->after('parent_id')->constrained('departments')->nullOnDelete();
        });
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropForeign(['department_id']));
        DB::table('budget_allocations')->delete();
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->foreign('department_id')->references('id')->on('departments')->cascadeOnDelete());
        Schema::table('offices', fn (Blueprint $table) => $table->dropColumn(['type', 'budget_fund']));
    }
};
