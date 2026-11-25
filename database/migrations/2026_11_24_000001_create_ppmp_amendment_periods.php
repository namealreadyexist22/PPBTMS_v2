<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** When PPMPs of a fiscal year may be amended (e.g. "1st amendment", Mar 23 – Apr 23), set by the budget officer. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ppmp_amendment_periods', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('fiscal_year');
            $table->string('name');
            $table->date('opens_on');
            $table->date('closes_on');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_amendment_periods');
    }
};
