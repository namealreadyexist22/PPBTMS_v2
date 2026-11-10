<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Budget Proposal: one per department and fiscal year. Each office writes its PAP titles there, with
 * their budget lines (MOOE not procured) and performance targets (Form A); the evaluator (Budget and
 * Treasury) gives the PAP codes. A PPMP picks a PAP from it for its projects: ppmp_paps becomes the
 * link between a PPMP and the Budget Proposal's PAP (code and title live on the PAP).
 *
 * Existing PPMP PAPs, their targets and budget lines move into draft Budget Proposals.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budget_proposals', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('department_id')->constrained('offices');
            $table->string('status', 20)->default('draft');   // draft | submitted | returned | evaluated
            $table->text('remarks')->nullable();               // why it was returned
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('evaluated_at')->nullable();
            $table->foreignId('evaluated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'department_id']);
        });

        Schema::create('budget_paps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_proposal_id')->constrained('budget_proposals')->cascadeOnDelete();
            $table->foreignId('office_id')->constrained('offices');   // the office that owns the PAP
            $table->string('code')->nullable();                       // given by the evaluator
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['budget_proposal_id', 'code']);
        });

        Schema::create('budget_pap_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_pap_id')->constrained('budget_paps')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('indicator', 500);
            $table->string('q1', 30)->nullable();
            $table->string('q2', 30)->nullable();
            $table->string('q3', 30)->nullable();
            $table->string('q4', 30)->nullable();
            $table->string('total', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_pap_id')->constrained('budget_paps')->cascadeOnDelete();
            $table->foreignId('expense_class_id')->constrained('expense_classes');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('fund_source_id')->constrained('fund_sources');
            $table->string('description', 1000);
            $table->decimal('amount', 15, 2);
            $table->string('remarks', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->foreignId('budget_pap_id')->nullable()->after('ppmp_id')->constrained('budget_paps')->cascadeOnDelete();
        });

        $this->moveExistingPaps();

        Schema::dropIfExists('ppmp_pap_indicators');
        Schema::dropIfExists('ppmp_budget_lines');
        // The new unique index first: MySQL keeps the old one while the ppmp_id foreign key needs an index
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->unique(['ppmp_id', 'budget_pap_id']);
        });
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->dropUnique(['ppmp_id', 'code']);
        });
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->dropColumn(['code', 'title', 'description']);
        });
    }

    /** Each PPMP PAP to its office's PAP in its department's Budget Proposal (one per code across PPMP versions). */
    protected function moveExistingPaps(): void
    {
        $offices = DB::table('offices')->get(['id', 'type', 'parent_id'])->keyBy('id');
        $departmentOf = function (int $officeId) use ($offices) {
            $root = null;
            for ($o = $offices->get($officeId); $o; $o = $o->parent_id ? $offices->get($o->parent_id) : null) {
                if ($o->type === 'department') {
                    return $o->id;
                }
                $root = $o->id;
            }

            return $root;
        };

        $proposals = [];
        $budgetPaps = [];   // "proposal-office-code" => id
        // Newest PPMP versions first: their targets and lines are the ones kept
        $rows = DB::table('ppmp_paps')->join('ppmps', 'ppmps.id', '=', 'ppmp_paps.ppmp_id')
            ->orderByDesc('ppmps.version')->orderByDesc('ppmps.id')
            ->get(['ppmp_paps.*', 'ppmps.office_id', 'ppmps.fiscal_year', 'ppmps.region', 'ppmps.created_by as ppmp_creator']);
        $linesTaken = [];   // budget pap ids whose lines came from a PPMP (one per region)

        foreach ($rows as $row) {
            $department = $departmentOf((int) $row->office_id);
            $proposalKey = "{$row->fiscal_year}-{$department}";
            $proposals[$proposalKey] ??= DB::table('budget_proposals')->insertGetId([
                'uuid' => (string) \Illuminate\Support\Str::uuid(), 'fiscal_year' => $row->fiscal_year, 'department_id' => $department,
                'status' => 'draft', 'created_by' => $row->ppmp_creator, 'created_at' => now(), 'updated_at' => now(),
            ]);

            $papKey = "{$proposals[$proposalKey]}-{$row->office_id}-{$row->code}";
            $isNew = ! isset($budgetPaps[$papKey]);
            if ($isNew) {
                $budgetPaps[$papKey] = DB::table('budget_paps')->insertGetId([
                    'budget_proposal_id' => $proposals[$proposalKey], 'office_id' => $row->office_id, 'code' => $row->code,
                    'title' => $row->title, 'sort_order' => $row->sort_order, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $budgetPapId = $budgetPaps[$papKey];
            DB::table('ppmp_paps')->where('id', $row->id)->update(['budget_pap_id' => $budgetPapId]);

            // Targets: from the newest version
            if ($isNew && Schema::hasTable('ppmp_pap_indicators')) {
                foreach (DB::table('ppmp_pap_indicators')->where('ppmp_pap_id', $row->id)->orderBy('sort_order')->get() as $i) {
                    DB::table('budget_pap_indicators')->insert([
                        'budget_pap_id' => $budgetPapId, 'description' => $i->description ?? null, 'indicator' => $i->indicator,
                        'q1' => $i->q1, 'q2' => $i->q2, 'q3' => $i->q3, 'q4' => $i->q4, 'total' => $i->total,
                        'sort_order' => $i->sort_order, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }

            // Budget lines: from the newest version of each region's PPMP
            $regionKey = "{$budgetPapId}-{$row->region}";
            if (! isset($linesTaken[$regionKey]) && Schema::hasTable('ppmp_budget_lines')) {
                $linesTaken[$regionKey] = true;
                foreach (DB::table('ppmp_budget_lines')->where('ppmp_pap_id', $row->id)->orderBy('sort_order')->get() as $l) {
                    DB::table('budget_lines')->insert([
                        'budget_pap_id' => $budgetPapId, 'expense_class_id' => $l->expense_class_id, 'location_id' => $l->location_id,
                        'fund_source_id' => $l->fund_source_id, 'description' => $l->description, 'amount' => $l->amount,
                        'remarks' => $l->remarks, 'sort_order' => $l->sort_order, 'created_at' => now(), 'updated_at' => now(),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->string('code')->nullable();
            $table->string('title')->nullable();
            $table->text('description')->nullable();
        });
        foreach (DB::table('budget_paps')->get() as $pap) {
            DB::table('ppmp_paps')->where('budget_pap_id', $pap->id)->update(['code' => $pap->code ?? ('PAP-' . $pap->id), 'title' => $pap->title]);
        }
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->unique(['ppmp_id', 'code']);
        });
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->dropForeign(['budget_pap_id']);
            $table->dropUnique(['ppmp_id', 'budget_pap_id']);
            $table->dropColumn('budget_pap_id');
        });

        // Targets and budget lines are not moved back
        Schema::create('ppmp_pap_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_pap_id')->constrained('ppmp_paps')->cascadeOnDelete();
            $table->text('description')->nullable();
            $table->string('indicator', 500);
            foreach (['q1', 'q2', 'q3', 'q4', 'total'] as $q) {
                $table->string($q, 30)->nullable();
            }
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
        Schema::create('ppmp_budget_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_id')->constrained('ppmps')->cascadeOnDelete();
            $table->foreignId('ppmp_pap_id')->constrained('ppmp_paps')->cascadeOnDelete();
            $table->foreignId('expense_class_id')->constrained('expense_classes');
            $table->foreignId('location_id')->nullable()->constrained('locations')->nullOnDelete();
            $table->foreignId('fund_source_id')->constrained('fund_sources');
            $table->string('description', 1000);
            $table->decimal('amount', 15, 2);
            $table->string('remarks', 1000)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::dropIfExists('budget_lines');
        Schema::dropIfExists('budget_pap_indicators');
        Schema::dropIfExists('budget_paps');
        Schema::dropIfExists('budget_proposals');
    }
};
