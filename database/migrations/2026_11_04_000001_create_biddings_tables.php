<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Competitive (public) bidding: Competitive Bidding lines of submitted PRs / JRs of one region and one
        // fund source, grouped into lots. Numbered YYYY-FUND-NNNN (series per year and fund) when the BAC
        // receives it. Its steps (pre-procurement conference, advertisement, pre-bid, bidding, ...) are kept
        // in bidding_events; the current dates and links are here.
        Schema::create('biddings', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('bid_no', 30)->nullable();
            $table->unsignedSmallInteger('series_year')->nullable();
            $table->unsignedInteger('series')->nullable();
            $table->foreignId('fund_source_id')->nullable()->constrained('fund_sources')->nullOnDelete();
            $table->string('region', 10);
            $table->string('title', 500)->nullable();
            $table->string('status', 20)->default('draft');   // draft, ongoing, cancelled
            $table->string('stage', 30)->nullable();           // the last step recorded
            $table->decimal('total_abc', 15, 2)->default(0);
            // Current dates and links (each change is in bidding_events)
            $table->date('received_at')->nullable();
            $table->dateTime('preproc_at')->nullable();
            $table->date('preproc_held_at')->nullable();
            $table->date('advertised_at')->nullable();
            $table->string('philgeps_link', 1000)->nullable();
            $table->string('website_link', 1000)->nullable();
            $table->dateTime('prebid_at')->nullable();
            $table->date('prebid_held_at')->nullable();
            $table->dateTime('bidding_at')->nullable();
            $table->unsignedSmallInteger('bidding_resets')->default(0);
            $table->date('bidding_held_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['series_year', 'fund_source_id', 'series'], 'bidding_number_unique');
            $table->index(['region', 'status']);
        });

        // A lot: awarded on its own. Its ABC is the total of its lines.
        Schema::create('bidding_lots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_id')->constrained('biddings')->cascadeOnDelete();
            $table->unsignedSmallInteger('lot_no');
            $table->string('title', 500)->nullable();
            $table->decimal('abc', 15, 2)->default(0);
            $table->timestamps();

            $table->unique(['bidding_id', 'lot_no']);
        });

        // A PR / JR line in a lot. A line is in at most one bidding that is not cancelled (checked in BiddingService).
        Schema::create('bidding_lot_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_lot_id')->constrained('bidding_lots')->cascadeOnDelete();
            $table->foreignId('purchase_request_item_id')->constrained('purchase_request_items');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['bidding_lot_id', 'purchase_request_item_id']);
        });

        // Each step recorded, with what was set (dates, links, a re-set bidding date and why): the history.
        Schema::create('bidding_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bidding_id')->constrained('biddings')->cascadeOnDelete();
            $table->string('step', 30);
            $table->dateTime('happened_at')->nullable();   // the step's own date (e.g. when the conference was held)
            $table->json('details')->nullable();
            $table->string('remarks', 1000)->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['bidding_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bidding_events');
        Schema::dropIfExists('bidding_lot_items');
        Schema::dropIfExists('bidding_lots');
        Schema::dropIfExists('biddings');
    }
};
