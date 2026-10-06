<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Departments as given by the Budget office: each gets one budget shared by its offices. */
    public const DEPARTMENTS = [
        '01000', '02000', '03000', '04000', '05000', '06000', '06010', '06550', '07000', '07020', '07560',
        '08000', '08010', '08560', '09000', '10000', '11000', '12000', '13000', '14000', '15000',
    ];

    public function up(): void
    {
        // A department receives the budget allocation; every office under it shares that budget
        Schema::table('offices', function (Blueprint $table) {
            $table->boolean('is_department')->default(false)->after('is_consolidating');
        });

        DB::table('offices')->whereIn('code', self::DEPARTMENTS)->update(['is_department' => true]);
    }

    public function down(): void
    {
        Schema::table('offices', fn (Blueprint $table) => $table->dropColumn('is_department'));
    }
};
