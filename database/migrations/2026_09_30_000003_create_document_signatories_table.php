<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Who signed what, with name/designation frozen at signing time.
        // Shared by PPMP, APP, PR, RFQ, AQ, NOA, ... via morph.
        Schema::create('document_signatories', function (Blueprint $table) {
            $table->id();
            $table->morphs('signable');
            $table->string('role');                    // prepared, submitted, approved, returned, ...
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name_snapshot');
            $table->string('designation_snapshot')->nullable();
            $table->timestamp('signed_at');
            $table->text('remarks')->nullable();
            $table->timestamps();

            $table->index(['signable_type', 'signable_id', 'role']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_signatories');
    }
};
