<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // GPPB Market Scoping Checklist per procurement project: period, activities, parameters
        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->json('market_scoping')->nullable()->after('supporting_documents');
        });

        // Files attached to a procurement project (market survey, specs, ...). An amendment copies
        // the rows; the file itself is shared and removed only when no row points to it.
        Schema::create('ppmp_item_attachments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_item_id')->constrained('ppmp_items')->cascadeOnDelete();
            $table->string('kind', 30);
            $table->string('original_name');
            $table->string('path');
            $table->string('mime_type', 120)->nullable();
            $table->unsignedBigInteger('size')->default(0);
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index('path');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_item_attachments');
        Schema::table('ppmp_items', fn (Blueprint $table) => $table->dropColumn('market_scoping'));
    }
};
