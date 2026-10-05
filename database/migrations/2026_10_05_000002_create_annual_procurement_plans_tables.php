<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Annual Procurement Plan: one per fiscal year and region (LM by the BAC, Visayas by
        // the Regional BAC). Updating an approved APP creates the next version.
        Schema::create('annual_procurement_plans', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('region', 5);
            $table->unsignedSmallInteger('version')->default(1);
            $table->string('type')->default('final');           // indicative | final | updated
            $table->string('status')->default('draft');         // draft | submitted | recommended | approved | superseded
            $table->foreignId('updated_from_id')->nullable()->constrained('annual_procurement_plans')->nullOnDelete();
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'region', 'version']);
        });

        // One APP line (procurement project), the columns of the APP form.
        // Several similar PPMP projects can be grouped into one line.
        Schema::create('app_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_procurement_plan_id')->constrained('annual_procurement_plans')->cascadeOnDelete();
            $table->string('pap_code')->nullable();             // grouping header, e.g. 26-06030-01
            $table->string('pap_title')->nullable();
            $table->boolean('is_cse')->default(false);          // Common-Use Supplies & Equipment from PS-DBM
            $table->text('project_title');                      // column 1
            $table->text('end_user');                           // column 2
            $table->text('description');                        // column 3
            $table->foreignId('procurement_mode_id')->constrained('procurement_modes');   // column 4
            $table->boolean('early_procurement')->default(false);                         // column 5
            $table->string('bid_criteria')->nullable();         // column 6, e.g. LCRB
            $table->date('proc_start');                         // column 7
            $table->date('proc_end');                           // column 8
            $table->foreignId('fund_source_id')->constrained('fund_sources');             // column 9
            $table->decimal('estimated_budget', 15, 2)->default(0);                       // column 10 (sum of its PPMP projects)
            $table->string('procurement_strategy')->nullable(); // column 11
            $table->text('remarks')->nullable();                // column 12
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // Which PPMP projects make up each APP line; a PPMP project is in at most one line per APP
        Schema::create('app_item_ppmp_item', function (Blueprint $table) {
            $table->id();
            $table->foreignId('annual_procurement_plan_id')->constrained('annual_procurement_plans')->cascadeOnDelete();
            $table->foreignId('app_item_id')->constrained('app_items')->cascadeOnDelete();
            $table->foreignId('ppmp_item_id')->constrained('ppmp_items')->cascadeOnDelete();

            $table->unique(['annual_procurement_plan_id', 'ppmp_item_id'], 'app_ppmp_item_unique');   // short name: MySQL max 64 chars
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_item_ppmp_item');
        Schema::dropIfExists('app_items');
        Schema::dropIfExists('annual_procurement_plans');
    }
};
