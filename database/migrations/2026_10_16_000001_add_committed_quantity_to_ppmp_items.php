<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Quantity charged by submitted PRs / JRs, kept next to committed_amount and under the
        // same row lock, so two requests submitted at once cannot both take the last units
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->decimal('committed_quantity', 12, 2)->default(0)->after('committed_amount');
        });

        // What submitted requests already hold, on every version of the line (same line_uuid)
        DB::table('purchase_request_items as i')
            ->join('purchase_requests as r', 'r.id', '=', 'i.purchase_request_id')
            ->where('r.status', 'submitted')
            ->groupBy('i.line_uuid')
            ->select('i.line_uuid', DB::raw('SUM(i.quantity) as quantity'))
            ->get()
            ->each(fn ($row) => DB::table('ppmp_items')->where('line_uuid', $row->line_uuid)
                ->update(['committed_quantity' => $row->quantity]));
    }

    public function down(): void
    {
        Schema::table('ppmp_items', fn (Blueprint $table) => $table->dropColumn('committed_quantity'));
    }
};
