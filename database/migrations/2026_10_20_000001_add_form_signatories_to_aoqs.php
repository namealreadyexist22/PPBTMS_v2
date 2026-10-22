<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // The office AOQ form: a technical comment from the evaluator and the Prepared / Evaluated /
        // Certified signatories above the BAC's approval.
        Schema::table('aoqs', function (Blueprint $table) {
            $table->text('technical_comment')->nullable()->after('remarks');
            $table->string('prepared_by_name')->nullable()->after('technical_comment');
            $table->string('prepared_by_designation')->nullable()->after('prepared_by_name');
            $table->string('evaluated_by_name')->nullable()->after('prepared_by_designation');
            $table->string('evaluated_by_designation')->nullable()->after('evaluated_by_name');
            $table->string('certified_by_name')->nullable()->after('evaluated_by_designation');
            $table->string('certified_by_designation')->nullable()->after('certified_by_name');
        });
    }

    public function down(): void
    {
        Schema::table('aoqs', function (Blueprint $table) {
            $table->dropColumn([
                'technical_comment', 'prepared_by_name', 'prepared_by_designation', 'evaluated_by_name',
                'evaluated_by_designation', 'certified_by_name', 'certified_by_designation',
            ]);
        });
    }
};
