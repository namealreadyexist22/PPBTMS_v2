<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Approved budget per office, fiscal year and fund (COB / SIDA), split into MOOE and CO.
        // An office's allocation covers the PPMPs of everything under it; offices below can get
        // their own (smaller) allocation out of it.
        Schema::create('budget_allocations', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('office_id')->constrained('offices');
            $table->string('fund_group', 10);
            $table->decimal('mooe_amount', 15, 2)->default(0);
            $table->decimal('co_amount', 15, 2)->default(0);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['fiscal_year', 'office_id', 'fund_group']);
        });

        // Every set / realignment of an allocation, with the reason
        Schema::create('budget_allocation_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('budget_allocation_id')->constrained('budget_allocations')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->decimal('old_mooe', 15, 2)->nullable();
            $table->decimal('old_co', 15, 2)->nullable();
            $table->decimal('new_mooe', 15, 2);
            $table->decimal('new_co', 15, 2);
            $table->text('reason')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budget_allocation_changes');
        Schema::dropIfExists('budget_allocations');
    }
};
