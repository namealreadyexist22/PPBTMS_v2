<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // An office asks the office that procures an item (its category's designated unit, e.g. MIS for
        // ICT Equipment) for a quantity for a fiscal year. That office approves all, part or none of it;
        // what it approves goes to the distribution of the PPMP project it picks.
        Schema::create('item_requests', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('request_no', 20);                  // IR 2027-0001 (series per fiscal year)
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedInteger('series');
            $table->foreignId('requesting_office_id')->constrained('offices');
            $table->foreignId('procuring_office_id')->constrained('offices');
            $table->foreignId('item_id')->constrained('items');
            $table->decimal('quantity', 12, 2);
            $table->text('purpose')->nullable();
            $table->string('status', 20)->default('pending');
            $table->decimal('approved_quantity', 12, 2)->nullable();
            $table->string('decision_remarks', 1000)->nullable();
            $table->foreignId('ppmp_item_id')->nullable()->constrained('ppmp_items')->nullOnDelete();
            $table->uuid('line_uuid')->nullable()->index();   // the project line its approved quantity went to
            $table->foreignId('requested_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['fiscal_year', 'series']);
            $table->index(['procuring_office_id', 'status']);
            $table->index(['requesting_office_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('item_requests');
    }
};
