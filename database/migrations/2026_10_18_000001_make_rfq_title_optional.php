<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The title is only a label in the system (not printed); without one the RFQ is named after its items
        Schema::table('rfqs', fn (Blueprint $table) => $table->string('title', 500)->nullable()->change());
    }

    public function down(): void
    {
        Schema::table('rfqs', fn (Blueprint $table) => $table->string('title', 500)->nullable(false)->default('')->change());
    }
};
