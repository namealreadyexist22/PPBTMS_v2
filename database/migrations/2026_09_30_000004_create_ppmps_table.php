<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Project Procurement Management Plan (GPPB revised format).
        // One per office per fiscal year; amendments create a new version.
        Schema::create('ppmps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('ppmp_no')->unique();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('office_id')->constrained('offices');
            $table->string('type')->default('indicative');      // indicative | final
            $table->unsignedSmallInteger('version')->default(1);
            $table->foreignId('amended_from_id')->nullable()->constrained('ppmps')->nullOnDelete();
            $table->string('status')->default('draft');         // draft | submitted | returned | approved | superseded
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['office_id', 'fiscal_year', 'version']);
            $table->index(['fiscal_year', 'status']);
        });

        Schema::create('ppmp_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_id')->constrained('ppmps')->cascadeOnDelete();
            // Stays the same across amended versions so PR charges follow the line.
            $table->uuid('line_uuid')->index();
            $table->foreignId('item_id')->nullable()->constrained('items')->nullOnDelete();

            $table->text('description');                        // general description and objective
            $table->string('project_type');                     // goods | infrastructure | consulting
            $table->decimal('quantity', 12, 2)->nullable();
            $table->foreignId('unit_id')->nullable()->constrained('units');
            $table->string('quantity_size')->nullable();        // free-text "quantity and size", e.g. "1 lot"
            $table->foreignId('procurement_mode_id')->constrained('procurement_modes');
            $table->boolean('pre_proc_conference')->default(false);
            $table->date('proc_start');                         // start of procurement activity (month)
            $table->date('proc_end');                           // end of procurement activity (month)
            $table->string('delivery_period')->nullable();      // expected delivery / implementation period
            $table->foreignId('fund_source_id')->constrained('fund_sources');
            $table->decimal('estimated_budget', 15, 2);
            // Amount already charged by approved-for-processing PR lines (maintained by PpmpBudgetService).
            $table->decimal('committed_amount', 15, 2)->default(0);
            $table->text('supporting_documents')->nullable();
            $table->text('remarks')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ppmp_id', 'line_uuid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_items');
        Schema::dropIfExists('ppmps');
    }
};
