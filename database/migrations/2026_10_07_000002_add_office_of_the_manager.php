<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Manager III is a position. The unit that prepares a department manager's PPMP is its
     * "Office of the Manager", a division under the department. In departments that have
     * divisions, that division takes over the department's office number (e.g. 05000), its
     * PPMPs and its users; the department becomes the grouping that holds the budget and the
     * head, and needs no office number. Single-unit departments (e.g. LEGAL) stay as they are.
     */
    public function up(): void
    {
        Schema::table('offices', function (Blueprint $table) {
            $table->string('code')->nullable()->change();
        });

        $departments = DB::table('offices')->where('type', 'department')->whereNotNull('code')->get();

        foreach ($departments as $department) {
            $hasDivisions = DB::table('offices')->where('parent_id', $department->id)->where('type', '!=', 'department')->exists();

            if (! $hasDivisions) {
                continue;
            }

            DB::table('offices')->where('id', $department->id)->update(['code' => null]);

            $now = now();
            $manager = DB::table('offices')->insertGetId([
                'code'             => $department->code,
                'type'             => 'division',
                'acronym'          => trim(($department->acronym ? "{$department->acronym}-" : '') . 'OM'),
                'name'             => 'OFFICE OF THE MANAGER',
                'parent_id'        => $department->id,
                'is_consolidating' => false,
                'is_active'        => $department->is_active,
                'created_at'       => $now,
                'updated_at'       => $now,
            ]);

            DB::table('ppmps')->where('office_id', $department->id)->update(['office_id' => $manager]);
            DB::table('users')->where('office_id', $department->id)->update(['office_id' => $manager]);
            DB::table('office_user')->where('office_id', $department->id)->update(['office_id' => $manager]);
        }
    }

    public function down(): void
    {
        foreach (DB::table('offices')->where('name', 'OFFICE OF THE MANAGER')->get() as $manager) {
            DB::table('ppmps')->where('office_id', $manager->id)->update(['office_id' => $manager->parent_id]);
            DB::table('users')->where('office_id', $manager->id)->update(['office_id' => $manager->parent_id]);
            DB::table('office_user')->where('office_id', $manager->id)->update(['office_id' => $manager->parent_id]);
            DB::table('offices')->where('id', $manager->id)->delete();
            DB::table('offices')->where('id', $manager->parent_id)->update(['code' => $manager->code]);
        }
    }
};
