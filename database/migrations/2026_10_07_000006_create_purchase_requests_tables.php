<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Purchase Requests (goods) and Job Requests (services / repairs), charged to one PAP of an
        // approved PPMP. Number YYYY-MM-XXXX, series per kind and year, given on submit. A revision
        // keeps the number (Rev. 1, 2, ...) and its signatories, and replaces the one before it.
        Schema::create('purchase_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('kind', 2);                       // pr | jr
            $table->string('request_no', 20)->nullable();    // 2026-06-1218, given on submit
            $table->unsignedSmallInteger('series_year')->nullable();
            $table->unsignedInteger('series')->nullable();
            $table->unsignedSmallInteger('revision')->default(0);
            $table->foreignId('revised_from_id')->nullable()->constrained('purchase_requests')->nullOnDelete();
            $table->unsignedSmallInteger('fiscal_year');
            $table->foreignId('office_id')->constrained('offices');
            $table->foreignId('ppmp_id')->constrained('ppmps');
            $table->foreignId('ppmp_pap_id')->constrained('ppmp_paps');
            $table->string('jr_type', 40)->nullable();
            $table->text('purpose')->nullable();
            $table->string('sai_no', 50)->nullable();         // Supplies Availability Inquiry (PR)
            $table->date('sai_date')->nullable();
            $table->string('status', 20)->default('draft');   // draft, submitted, superseded, cancelled
            $table->decimal('total_amount', 15, 2)->default(0);
            $table->string('requested_by_name')->nullable();
            $table->string('requested_by_designation')->nullable();
            $table->string('approved_by_name')->nullable();
            $table->string('approved_by_designation')->nullable();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['kind', 'series_year', 'series', 'revision'], 'purchase_request_number_unique');
            $table->index(['kind', 'status']);
        });

        Schema::create('purchase_request_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->foreignId('ppmp_item_id')->constrained('ppmp_items');
            $table->uuid('line_uuid')->index();               // the PPMP project line, across amendments
            $table->string('stock_no', 50)->nullable();       // Stock No. (PR) / Property No. (JR)
            $table->string('unit', 50)->nullable();
            $table->string('description', 500);
            $table->text('specifications')->nullable();
            $table->decimal('quantity', 12, 2);
            $table->decimal('unit_cost', 15, 2);
            $table->decimal('total_cost', 15, 2);
            $table->text('nature_of_work')->nullable();       // JR
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_items');
        Schema::dropIfExists('purchase_requests');
    }
};
