<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** A budget line's description is a label only (the printouts total by expense class): optional. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('description', 1000)->nullable()->change();
        });
    }

    public function down(): void
    {
        DB::table('budget_lines')->whereNull('description')->update(['description' => '']);
        Schema::table('budget_lines', function (Blueprint $table) {
            $table->string('description', 1000)->nullable(false)->change();
        });
    }
};
