<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Once the BAC has a request on an RFQ it is in process: the office can revise or cancel it only
        // after the BAC returns it (its items taken off the RFQ), for revision or for cancellation.
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->timestamp('returned_at')->nullable()->after('cancel_reason');
            $table->string('return_for', 20)->nullable()->after('returned_at');   // revision | cancellation
            $table->string('return_reason', 1000)->nullable()->after('return_for');
            $table->foreignId('returned_by')->nullable()->after('return_reason')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('purchase_requests', function (Blueprint $table) {
            $table->dropConstrainedForeignId('returned_by');
            $table->dropColumn(['returned_at', 'return_for', 'return_reason']);
        });
    }
};
