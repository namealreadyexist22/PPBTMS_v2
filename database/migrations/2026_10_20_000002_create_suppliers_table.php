<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Supplier directory: picked on an AOQ so its details need not be typed again. The AOQ keeps
        // its own copy of the name and address (what was printed), linked back to the directory.
        Schema::create('suppliers', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->string('address', 500)->nullable();
            $table->string('tin', 50)->nullable();
            $table->string('contact')->nullable();
            $table->string('philgeps_no', 50)->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('aoq_suppliers', function (Blueprint $table) {
            $table->foreignId('supplier_id')->nullable()->after('aoq_id')->constrained('suppliers')->nullOnDelete();
        });

        // Start the directory from the suppliers already on abstracts (latest details per name)
        $seen = [];
        foreach (DB::table('aoq_suppliers')->orderByDesc('id')->get() as $row) {
            $key = mb_strtolower(trim($row->name));
            if (! isset($seen[$key])) {
                $seen[$key] = DB::table('suppliers')->insertGetId([
                    'name' => trim($row->name), 'address' => $row->address, 'tin' => $row->tin, 'contact' => $row->contact,
                    'is_active' => true, 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            DB::table('aoq_suppliers')->where('id', $row->id)->update(['supplier_id' => $seen[$key]]);
        }
    }

    public function down(): void
    {
        Schema::table('aoq_suppliers', function (Blueprint $table) {
            $table->dropConstrainedForeignId('supplier_id');
        });
        Schema::dropIfExists('suppliers');
    }
};
