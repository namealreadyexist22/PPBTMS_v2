<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Performance Contract (COB Form A): a PAP's brief description and its performance indicators with
 * quarterly physical targets. Also the two COB lines that were not departments yet: the (central)
 * Bids and Awards Committee and Fixed Utilities, added only when missing.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ppmp_paps', function (Blueprint $table) {
            $table->text('description')->nullable()->after('title');
        });

        Schema::create('ppmp_pap_indicators', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_pap_id')->constrained('ppmp_paps')->cascadeOnDelete();
            $table->string('indicator', 500);
            // Targets as written on the form: "100%", "3", or blank
            $table->string('q1', 30)->nullable();
            $table->string('q2', 30)->nullable();
            $table->string('q3', 30)->nullable();
            $table->string('q4', 30)->nullable();
            $table->string('total', 30)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        foreach ([['16000', 'BAC', 'BIDS AND AWARDS COMMITTEE'], ['17000', 'FU', 'FIXED UTILITIES']] as [$code, $acronym, $name]) {
            $exists = DB::table('offices')->where('code', $code)
                ->orWhere(fn ($q) => $q->where('type', 'department')->where('acronym', $acronym))->exists();

            if (! $exists && Schema::hasTable('offices') && DB::table('offices')->exists()) {
                DB::table('offices')->insert([
                    'code' => $code, 'acronym' => $acronym, 'name' => $name, 'type' => 'department',
                    'budget_fund' => 'regular', 'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_pap_indicators');
        Schema::table('ppmp_paps', fn (Blueprint $table) => $table->dropColumn('description'));
        // The two departments stay (they may have allocations or PPMPs by now)
    }
};
