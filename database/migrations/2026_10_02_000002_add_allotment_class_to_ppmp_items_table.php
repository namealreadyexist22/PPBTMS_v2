<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // MOOE or Capital Outlay per project (a PAP can have both); used by the APP's MOOE / CO columns
    public function up(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->string('allotment_class', 10)->default('mooe')->after('fund_source_id');
        });
    }

    public function down(): void
    {
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropColumn('allotment_class');
        });
    }
};
