<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Responsibility centers / end-user units. Replaces the old resp_codes table.
        Schema::create('offices', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();          // responsibility center code
            $table->string('name');
            $table->string('division')->nullable();
            $table->string('section')->nullable();
            // Division -> sections. A section's PPMP is approved by its division head.
            $table->foreignId('parent_id')->nullable()->constrained('offices')->nullOnDelete();
            $table->foreignId('head_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('designation')->nullable()->after('minitial');
            $table->foreignId('office_id')->nullable()->after('designation')->constrained('offices')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('office_id');
            $table->dropColumn('designation');
        });

        Schema::dropIfExists('offices');
    }
};
