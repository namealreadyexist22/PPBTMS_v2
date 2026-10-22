<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Quotes saved before the TWG evaluation had "meets specs" ticked by default on the BAC's form.
     * On abstracts never sent to the TWG that is no finding: clear it for the TWG.
     */
    public function up(): void
    {
        $aoqIds = DB::table('aoqs')->where('status', 'draft')->whereNull('sent_to_twg_at')->pluck('id');
        $supplierIds = DB::table('aoq_suppliers')->whereIn('aoq_id', $aoqIds)->pluck('id');

        DB::table('aoq_quotes')->whereIn('aoq_supplier_id', $supplierIds)->update(['is_compliant' => null]);
    }

    public function down(): void
    {
        // The defaults cleared were not findings; nothing to restore
    }
};
