<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    // The user's region lives in the existing users.categories field instead of its own
    // column: 1 = Luzon/Mindanao, 2 = Visayas.
    public function up(): void
    {
        DB::table('users')->where('region', 'vis')->update(['categories' => 2]);
        DB::table('users')->where('region', '!=', 'vis')->whereNotIn('categories', [1, 2])->update(['categories' => 1]);

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('region', 5)->default('lm')->after('office_id');
        });

        DB::table('users')->where('categories', 2)->update(['region' => 'vis']);
    }
};
