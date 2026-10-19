<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Abstract of Quotations of an issued RFQ (one per RFQ, numbered like it). The BAC records
        // the quotations received; on finalizing, each item is awarded to its lowest responsive quote.
        Schema::create('aoqs', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('rfq_id')->unique()->constrained('rfqs')->cascadeOnDelete();
            $table->string('region', 10);
            $table->string('status', 20)->default('draft');   // draft, awarded
            $table->dateTime('opened_at')->nullable();          // opening of the quotations
            $table->text('remarks')->nullable();
            // Printed signatories (defaults from Signatories & BAC, changeable on the AOQ)
            $table->string('bac_chair_name')->nullable();
            $table->string('bac_vice_chair_name')->nullable();
            $table->json('bac_members')->nullable();            // list of names
            $table->string('hope_name')->nullable();
            $table->timestamp('awarded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['region', 'status']);
        });

        // A supplier that submitted a quotation. Not responsive (e.g. no PhilGEPS registration): none of its quotes count.
        Schema::create('aoq_suppliers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aoq_id')->constrained('aoqs')->cascadeOnDelete();
            $table->string('name');
            $table->string('address', 500)->nullable();
            $table->string('tin', 50)->nullable();
            $table->string('contact')->nullable();
            $table->boolean('is_responsive')->default(true);
            $table->string('remarks', 500)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        // A supplier's offer for one RFQ item; no unit price = no quote for that item.
        Schema::create('aoq_quotes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aoq_supplier_id')->constrained('aoq_suppliers')->cascadeOnDelete();
            $table->foreignId('rfq_item_id')->constrained('rfq_items')->cascadeOnDelete();
            $table->decimal('unit_price', 15, 2)->nullable();
            $table->string('brand', 500)->nullable();
            $table->boolean('is_compliant')->default(true);   // meets the specifications
            $table->boolean('is_awarded')->default(false);
            $table->timestamps();

            $table->unique(['aoq_supplier_id', 'rfq_item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('aoq_quotes');
        Schema::dropIfExists('aoq_suppliers');
        Schema::dropIfExists('aoqs');
    }
};
