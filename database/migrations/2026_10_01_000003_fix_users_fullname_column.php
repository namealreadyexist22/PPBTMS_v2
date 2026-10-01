<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * fullname was concat(fname, " ", minitial, " ", lname). MySQL's CONCAT returns
     * NULL when any part is NULL, so users without a middle initial had no fullname.
     * Rebuild it as "Juan M. Cruz" / "Juan Cruz", skipping empty parts.
     */
    public function up(): void
    {
        $this->rebuild(match (DB::getDriverName()) {
            'sqlite' => "trim(fname || ' ' || coalesce(nullif(minitial, '') || '. ', '') || lname)",
            default  => "concat_ws(' ', fname, concat(nullif(minitial, ''), '.'), lname)",
        });
    }

    public function down(): void
    {
        $this->rebuild('concat(fname, " ", minitial, " ", lname)');
    }

    protected function rebuild(string $expression): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('fullname');
        });

        Schema::table('users', function (Blueprint $table) use ($expression) {
            $table->string('fullname')->virtualAs($expression)->after('minitial');
        });
    }
};
