<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Region of a department (lm / vis), inherited by the units under it. Not set = Luzon/Mindanao.
        // Visayas units have their own Division PPMP and APP (Regional BAC).
        Schema::table('offices', function (Blueprint $table) {
            $table->string('region', 3)->nullable()->after('budget_fund');
        });

        DB::table('offices')->where('type', 'department')->whereIn('acronym', ['AFD-VIS', 'RDE-VIS', 'RD-VIS', 'RBAC'])->update(['region' => 'vis']);
        DB::table('offices')->where('type', 'department')->whereIn('acronym', ['AFD-LM', 'RDE-LM', 'RD-LM'])->update(['region' => 'lm']);
    }

    public function down(): void
    {
        Schema::table('offices', fn (Blueprint $table) => $table->dropColumn('region'));
    }
};
