<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A title before the name (Atty., Engr., Dr., ...) and a suffix after it (Jr., Sr., III, ...),
     * part of fullname as printed on the documents: "Atty. Arvin C. Beñas", "Juan M. Cruz Jr.".
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('name_title', 20)->nullable()->after('id');
            $table->string('name_suffix', 10)->nullable()->after('lname');
        });

        $this->rebuild(match (DB::getDriverName()) {
            'sqlite' => "trim(coalesce(nullif(name_title, '') || ' ', '') || fname || ' ' || coalesce(nullif(minitial, '') || '. ', '') || lname || coalesce(' ' || nullif(name_suffix, ''), ''))",
            default  => "concat_ws(' ', nullif(name_title, ''), fname, concat(nullif(minitial, ''), '.'), lname, nullif(name_suffix, ''))",
        });
    }

    public function down(): void
    {
        $this->rebuild(match (DB::getDriverName()) {
            'sqlite' => "trim(fname || ' ' || coalesce(nullif(minitial, '') || '. ', '') || lname)",
            default  => "concat_ws(' ', fname, concat(nullif(minitial, ''), '.'), lname)",
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['name_title', 'name_suffix']);
        });
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
