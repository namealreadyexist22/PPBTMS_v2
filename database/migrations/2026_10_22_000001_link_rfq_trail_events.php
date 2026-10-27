<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /** "Put on RFQ 2026-0001" steps recorded without a link: link them to that RFQ, so the trail can show it. */
    public function up(): void
    {
        $events = DB::table('purchase_request_events')->whereIn('event', ['rfq_added', 'rfq_removed'])->whereNull('url')->get();

        foreach ($events as $event) {
            if (! preg_match('/RFQ (\d{4}-\d{4})/', $event->description, $m)) {
                continue;   // a draft's: no number to find it by
            }

            $uuid = DB::table('rfqs')->where('rfq_no', $m[1])->value('uuid');
            if ($uuid) {
                DB::table('purchase_request_events')->where('id', $event->id)->update(['url' => "/procurement/rfqs/{$uuid}"]);
            }
        }
    }

    public function down(): void
    {
        // The links stay: they point where the step happened
    }
};
