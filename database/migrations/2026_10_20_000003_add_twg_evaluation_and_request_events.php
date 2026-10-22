<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // TWG evaluation: the BAC sends the AOQ to the evaluators it assigns; they check each offer
        // against the specs (with the PDFs the suppliers gave) and complete it or return it.
        Schema::table('aoqs', function (Blueprint $table) {
            $table->timestamp('sent_to_twg_at')->nullable()->after('opened_at');
            $table->timestamp('evaluated_at')->nullable()->after('sent_to_twg_at');
            $table->foreignId('evaluated_by')->nullable()->after('evaluated_at')->constrained('users')->nullOnDelete();
            $table->string('return_reason', 1000)->nullable()->after('evaluated_by');   // last return to the BAC
        });

        Schema::create('aoq_evaluators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('aoq_id')->constrained('aoqs')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['aoq_id', 'user_id']);
        });

        // The supplier's whole quotation / proposal (PDF)
        Schema::table('aoq_suppliers', function (Blueprint $table) {
            $table->string('attachment_name')->nullable()->after('remarks');
            $table->string('attachment_path')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_path');
        });

        // The offer's brand / specs (PDF) and the TWG's finding; compliance is unknown until evaluated
        Schema::table('aoq_quotes', function (Blueprint $table) {
            $table->boolean('is_compliant')->nullable()->default(null)->change();
            $table->string('twg_remarks', 1000)->nullable()->after('is_compliant');
            $table->string('attachment_name')->nullable()->after('brand');
            $table->string('attachment_path')->nullable()->after('attachment_name');
            $table->unsignedBigInteger('attachment_size')->nullable()->after('attachment_path');
        });

        // Status trail of a PR / JR: what happened to it, through the RFQ, AOQ and award
        Schema::create('purchase_request_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_request_id')->constrained('purchase_requests')->cascadeOnDelete();
            $table->string('event', 40);
            $table->string('description', 1000);
            $table->string('remarks', 1000)->nullable();
            $table->string('url')->nullable();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();

            $table->index(['purchase_request_id', 'created_at']);
        });

        $this->backfillTrail();
    }

    /** The trail of requests made before it existed, from their dates. */
    protected function backfillTrail(): void
    {
        $rows = [];
        $add = function ($prId, $event, $description, $at, $userId = null, $remarks = null) use (&$rows) {
            if ($at) {
                $rows[] = ['purchase_request_id' => $prId, 'event' => $event, 'description' => $description, 'remarks' => $remarks, 'user_id' => $userId, 'created_at' => $at];
            }
        };

        foreach (DB::table('purchase_requests')->get() as $pr) {
            $add($pr->id, 'created', 'Prepared as a draft', $pr->created_at, $pr->created_by);
            $add($pr->id, 'submitted', 'Submitted', $pr->submitted_at);
            $add($pr->id, 'cancelled', 'Cancelled', $pr->cancelled_at, null, $pr->cancel_reason);
        }

        $rfqs = DB::table('rfqs')->join('rfq_items', 'rfq_items.rfq_id', '=', 'rfqs.id')
            ->join('purchase_request_items', 'purchase_request_items.id', '=', 'rfq_items.purchase_request_item_id')
            ->select('rfqs.*', 'purchase_request_items.purchase_request_id as pr_id')->get()
            ->unique(fn ($r) => $r->id . '-' . $r->pr_id);

        foreach ($rfqs as $rfq) {
            $title = 'RFQ ' . ($rfq->rfq_no ?? '(draft)');
            $add($rfq->pr_id, 'rfq_added', "Put on {$title}", $rfq->created_at, $rfq->created_by);
            $add($rfq->pr_id, 'rfq_issued', "{$title} issued to suppliers", $rfq->issued_at);
            $add($rfq->pr_id, 'rfq_cancelled', "{$title} cancelled", $rfq->cancelled_at, null, $rfq->cancel_reason);

            if ($aoq = DB::table('aoqs')->where('rfq_id', $rfq->id)->first()) {
                $add($rfq->pr_id, 'aoq_started', "Abstract of Quotations AOQ {$rfq->rfq_no} started", $aoq->created_at, $aoq->created_by);
                $add($rfq->pr_id, 'aoq_awarded', "AOQ {$rfq->rfq_no} awarded", $aoq->awarded_at);
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('purchase_request_events')->insert($chunk);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_request_events');
        Schema::table('aoq_quotes', function (Blueprint $table) {
            $table->dropColumn(['twg_remarks', 'attachment_name', 'attachment_path', 'attachment_size']);
        });
        Schema::table('aoq_suppliers', function (Blueprint $table) {
            $table->dropColumn(['attachment_name', 'attachment_path', 'attachment_size']);
        });
        Schema::dropIfExists('aoq_evaluators');
        Schema::table('aoqs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('evaluated_by');
            $table->dropColumn(['sent_to_twg_at', 'evaluated_at', 'return_reason']);
        });
    }
};
