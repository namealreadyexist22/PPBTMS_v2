<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * What the Corporate Operating Budget printouts need:
 *  - expense classes (Form B columns: Traveling, Training, Office Supplies, ...), defaulted on a project from its item category
 *  - locations (Quezon City, Bacolod, La Granja, ...) to break a PAP down on Forms B and C
 *  - deployment and justification of Capital Outlay projects (Form C)
 *  - budget-only lines: MOOE a PAP needs that is not procured (travel, training, representation, subsidies);
 *    they count toward the budget and the COB but never go to the APP, PRs or RFQs
 *  - Personnel Services of a department's allocation (Breakdown per Office / Department); not part of the PPMP budget check
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('expense_classes', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->unsignedInteger('sort_order')->default(0);   // column order on Form B
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('item_categories', function (Blueprint $table) {
            $table->foreignId('expense_class_id')->nullable()->after('name')->constrained('expense_classes')->nullOnDelete();
        });

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->foreignId('expense_class_id')->nullable()->after('allotment_class')->constrained('expense_classes')->nullOnDelete();
            $table->foreignId('location_id')->nullable()->after('expense_class_id')->constrained('locations')->nullOnDelete();
            $table->string('deployment', 500)->nullable()->after('location_id');
            $table->text('justification')->nullable()->after('deployment');
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

        Schema::table('budget_allocations', function (Blueprint $table) {
            $table->decimal('ps_amount', 15, 2)->default(0)->after('amount');
        });
    }

    public function down(): void
    {
        Schema::table('budget_allocations', fn (Blueprint $table) => $table->dropColumn('ps_amount'));
        Schema::dropIfExists('ppmp_budget_lines');
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('expense_class_id');
            $table->dropConstrainedForeignId('location_id');
            $table->dropColumn(['deployment', 'justification']);
        });
        Schema::table('item_categories', fn (Blueprint $table) => $table->dropConstrainedForeignId('expense_class_id'));
        Schema::dropIfExists('locations');
        Schema::dropIfExists('expense_classes');
    }
};
