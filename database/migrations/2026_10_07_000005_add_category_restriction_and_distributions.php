<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // A category only one unit may procure (e.g. ICT Equipment -> MIS), optionally only for one
        // fund (COB): SIDA departments still buy their own ICT out of SIDA
        Schema::table('item_categories', function (Blueprint $table) {
            $table->foreignId('restricted_office_id')->nullable()->after('name')->constrained('offices')->nullOnDelete();
            $table->string('restricted_fund_group', 10)->nullable()->after('restricted_office_id');   // null = every fund
        });

        // Who gets what: the procuring unit's assessment of which offices receive a project's items.
        // Not part of the PPMP / APP; keyed by the project line so it follows amendments, and later
        // used for issuance (ICS / PAR).
        Schema::create('ppmp_item_distributions', function (Blueprint $table) {
            $table->id();
            $table->uuid('line_uuid')->index();
            $table->foreignId('office_id')->constrained('offices');
            $table->decimal('quantity', 12, 2);
            $table->string('recipient')->nullable();   // end-user / accountable person, if known
            $table->string('remarks', 500)->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ppmp_item_distributions');
        Schema::table('item_categories', function (Blueprint $table) {
            $table->dropForeign(['restricted_office_id']);
            $table->dropColumn(['restricted_office_id', 'restricted_fund_group']);
        });
    }
};
