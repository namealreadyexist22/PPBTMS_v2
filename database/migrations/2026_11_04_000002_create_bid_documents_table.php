<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The documents each bidder's bid is checked for (eligibility, technical and financial); a lookup
        // kept in Settings > Procurement Lookups. Each bidder's checklist (with the uploaded copies) uses it.
        Schema::create('bid_documents', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bid_documents');
    }
};
