<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Form A: a PAP has several activities, each with its own brief description and one or more indicators.
 * The description goes on the indicator; a blank one continues the description above it.
 * The PAP's single description moves onto its first indicator.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_pap_indicators', function (Blueprint $table) {
            $table->text('description')->nullable()->after('ppmp_pap_id');
        });

        foreach (DB::table('ppmp_paps')->whereNotNull('description')->get(['id', 'description']) as $pap) {
            $first = DB::table('ppmp_pap_indicators')->where('ppmp_pap_id', $pap->id)->orderBy('sort_order')->orderBy('id')->first();
            if ($first) {
                DB::table('ppmp_pap_indicators')->where('id', $first->id)->whereNull('description')->update(['description' => $pap->description]);
                DB::table('ppmp_paps')->where('id', $pap->id)->update(['description' => null]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('ppmp_pap_indicators', fn (Blueprint $table) => $table->dropColumn('description'));
    }
};
