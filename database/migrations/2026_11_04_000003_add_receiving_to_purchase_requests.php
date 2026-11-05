<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The BAC receives the submitted PR / JR (its signed hard copy) before its items go on an RFQ or a bidding
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->timestamp('received_at')->nullable()->after('submitted_at');
            $table->foreignId('received_by')->nullable()->after('received_at')->constrained('users')->nullOnDelete();
        });

        // Already in process (on an RFQ or in a bidding): received when submitted
        $inProcess = DB::table('purchase_request_items')
            ->whereIn('id', DB::table('rfq_items')->join('rfqs', 'rfqs.id', '=', 'rfq_items.rfq_id')->where('rfqs.status', '!=', 'cancelled')->select('rfq_items.purchase_request_item_id'))
            ->orWhereIn('id', DB::table('bidding_lot_items')->select('purchase_request_item_id'))
            ->distinct()->pluck('purchase_request_id');
        DB::table('purchase_requests')->whereIn('id', $inProcess)->whereNotNull('submitted_at')->update(['received_at' => DB::raw('submitted_at')]);

        // A bidding is numbered when the BAC creates it (receiving is now the PR's): drafts get their number,
        // an empty one (no fund source yet) is removed
        foreach (DB::table('biddings')->where('status', 'draft')->orderBy('id')->get() as $draft) {
            if (! $draft->fund_source_id) {
                DB::table('biddings')->where('id', $draft->id)->delete();
                continue;
            }
            $year = Carbon::parse($draft->created_at)->year;
            $code = strtoupper(DB::table('fund_sources')->where('id', $draft->fund_source_id)->value('code'));
            $series = (int) DB::table('biddings')->where('series_year', $year)->where('fund_source_id', $draft->fund_source_id)->max('series') + 1;
            DB::table('biddings')->where('id', $draft->id)->update([
                'status' => 'ongoing', 'series_year' => $year, 'series' => $series, 'bid_no' => sprintf('%d-%s-%04d', $year, $code, $series),
            ]);
        }
        DB::table('biddings')->where('stage', 'received')->update(['stage' => null]);
        DB::table('bidding_events')->where('step', 'received')->delete();

        Schema::table('biddings', function (Blueprint $table) {
            $table->dropColumn('received_at');
        });
    }

    public function down(): void
    {
        Schema::table('biddings', function (Blueprint $table) {
            $table->date('received_at')->nullable()->after('total_abc');
        });

        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('received_by');
            $table->dropColumn('received_at');
        });
    }
};
