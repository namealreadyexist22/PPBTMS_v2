<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Request for Quotation: items of one or more submitted PRs / JRs of one region and one
        // procurement mode, canvassed together. Quotation No. YYYY-NNNN (series per year), given on issue.
        // Suppliers are written on the printed copies by the canvassers.
        Schema::create('rfqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('rfq_no', 20)->nullable();
            $table->unsignedSmallInteger('series_year')->nullable();
            $table->unsignedInteger('series')->nullable();
            $table->string('region', 10);
            $table->foreignId('procurement_mode_id')->nullable()->constrained('procurement_modes')->nullOnDelete();
            $table->string('title', 500);
            $table->string('delivery_place', 500)->nullable();
            $table->string('delivery_period')->nullable();
            $table->string('price_validity')->nullable();
            $table->string('warranty')->nullable();
            $table->string('payment_terms')->nullable();
            $table->dateTime('submission_deadline')->nullable();
            $table->text('remarks')->nullable();
            $table->string('signatory_name')->nullable();
            $table->string('signatory_designation')->nullable();
            $table->string('status', 20)->default('draft');   // draft, issued, cancelled
            $table->decimal('total_abc', 15, 2)->default(0);
            $table->timestamp('issued_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series_year', 'series'], 'rfq_number_unique');
            $table->index(['region', 'status']);
        });

        // A PR / JR line on the RFQ. A line is on at most one RFQ that is not cancelled (checked in RfqService).
        Schema::create('rfq_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('rfq_id')->constrained('rfqs')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained('purchase_request_items');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['rfq_id', 'purchase_request_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rfq_items');
        Schema::dropIfExists('rfqs');
    }
};
