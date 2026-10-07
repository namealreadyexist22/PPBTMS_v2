<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Early Procurement Activity (RA 12009 Sec. 12): may be procured before the budget is
        // approved, once the Indicative APP is approved; no award until the funds are effective
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->boolean('is_epa')->default(false)->after('pre_proc_conference');
        });

        // Standard items: articles the agency regularly buys, with a standard unit cost and the
        // specifications set by the TWG, so every office buys them at the same specs and price
        Schema::table('items', function (Blueprint $table) {
            $table->string('project_type', 20)->default('goods')->after('item_category_id');
            $table->decimal('standard_unit_cost', 15, 2)->nullable()->after('project_type');
            $table->text('specifications')->nullable()->after('standard_unit_cost');
            $table->string('twg_reference')->nullable()->after('specifications');
            $table->date('twg_approved_at')->nullable()->after('twg_reference');
            $table->foreignId('updated_by')->nullable()->after('is_active')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('items', function (Blueprint $table) {
            $table->dropForeign(['updated_by']);
            $table->dropColumn(['project_type', 'standard_unit_cost', 'specifications', 'twg_reference', 'twg_approved_at', 'updated_by']);
        });
        Schema::table('ppmp_items', fn (Blueprint $table) => $table->dropColumn('is_epa'));
    }
};
