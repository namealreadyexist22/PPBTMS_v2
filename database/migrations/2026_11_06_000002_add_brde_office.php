<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Bioethanol Research, Development & Extension (BRDE), 05030 under PPSPD (COB: PAPs 05030-xx), added
 * when missing, and the Corporate(BRDE) fund source kept for it when that fund has no office yet.
 */
return new class extends Migration
{
    public function up(): void
    {
        $ppspd = DB::table('offices')->where('type', 'department')->where('acronym', 'PPSPD')->value('id');
        if (! $ppspd) {
            return;   // a fresh install: OfficeSeeder adds it
        }

        $brde = DB::table('offices')->where('code', '05030')->orWhere('acronym', 'BRDE')->value('id')
            ?? DB::table('offices')->insertGetId([
                'code' => '05030', 'acronym' => 'BRDE', 'name' => 'BIOETHANOL RESEARCH, DEVELOPMENT & EXTENSION',
                'type' => 'division', 'parent_id' => $ppspd, 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
            ]);

        DB::table('fund_sources')->where('code', 'BRDE')->whereNull('restricted_office_id')->update(['restricted_office_id' => $brde]);
    }

    public function down(): void
    {
        // The office stays (it may have PPMPs by now)
    }
};
