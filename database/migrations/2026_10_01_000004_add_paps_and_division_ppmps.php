<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The office whose head combines the PPMPs below it and submits them to BAC
        Schema::table('offices', function (Blueprint $table) {
            $table->boolean('is_consolidating')->default(false)->after('head_user_id');
        });

        // PAP (Program/Activity/Project) groups inside a section PPMP,
        // e.g. 26-05012-01 ICT Infrastructure Management
        Schema::create('ppmp_paps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ppmp_id')->constrained('ppmps')->cascadeOnDelete();
            $table->string('code');
            $table->string('title');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['ppmp_id', 'code']);
        });

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->foreignId('ppmp_pap_id')->nullable()->after('ppmp_id')->constrained('ppmp_paps')->cascadeOnDelete();
            $table->text('quantity_size')->nullable()->change();   // specifications can be long
        });

        // Existing projects (test data) go under one general PAP per PPMP
        foreach (DB::table('ppmps')->get() as $ppmp) {
            if (! DB::table('ppmp_items')->where('ppmp_id', $ppmp->id)->exists()) {
                continue;
            }

            $officeCode = DB::table('offices')->where('id', $ppmp->office_id)->value('code');
            $papId = DB::table('ppmp_paps')->insertGetId([
                'ppmp_id'    => $ppmp->id,
                'code'       => sprintf('%02d-%s-01', $ppmp->fiscal_year % 100, $officeCode),
                'title'      => 'General',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
            DB::table('ppmp_items')->where('ppmp_id', $ppmp->id)->update(['ppmp_pap_id' => $papId]);
        }

        // The combined PPMP a consolidating office submits to BAC: "PPMP NO. 1", 2, 3...
        // Created when its head approves; each approval of amendments makes the next number.
        Schema::create('division_ppmps', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('office_id')->constrained('offices');
            $table->unsignedSmallInteger('fiscal_year');
            $table->unsignedSmallInteger('ppmp_number');
            $table->string('type')->default('final');            // indicative | final
            $table->string('status')->default('approved');       // approved | superseded
            $table->decimal('total_budget', 15, 2)->default(0);
            $table->text('remarks')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['office_id', 'fiscal_year', 'ppmp_number']);
        });

        // Which section PPMP versions make up each Division PPMP number
        Schema::create('division_ppmp_ppmp', function (Blueprint $table) {
            $table->id();
            $table->foreignId('division_ppmp_id')->constrained('division_ppmps')->cascadeOnDelete();
            $table->foreignId('ppmp_id')->constrained('ppmps')->cascadeOnDelete();

            $table->unique(['division_ppmp_id', 'ppmp_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('division_ppmp_ppmp');
        Schema::dropIfExists('division_ppmps');

        Schema::table('ppmp_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('ppmp_pap_id');
            $table->string('quantity_size')->nullable()->change();
        });

        Schema::dropIfExists('ppmp_paps');

        Schema::table('offices', function (Blueprint $table) {
            $table->dropColumn('is_consolidating');
        });
    }
};
