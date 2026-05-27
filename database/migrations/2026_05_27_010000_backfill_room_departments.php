<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $fallbackDepartmentId = DB::table('departments')
            ->where('name', '總務處')
            ->value('id')
            ?? DB::table('departments')->orderBy('id')->value('id');

        if ($fallbackDepartmentId === null) {
            return;
        }

        DB::table('rooms')
            ->whereNull('department_id')
            ->update(['department_id' => $fallbackDepartmentId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
