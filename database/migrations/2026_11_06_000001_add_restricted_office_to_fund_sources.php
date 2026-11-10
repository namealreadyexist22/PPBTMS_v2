<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** A fund source only one office (and the units under it) may charge, e.g. Corporate(BRDE) for the BRDE section. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fund_sources', function (Blueprint $table) {
            $table->foreignId('restricted_office_id')->nullable()->after('fund_group')->constrained('offices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('fund_sources', fn (Blueprint $table) => $table->dropConstrainedForeignId('restricted_office_id'));
    }
};
