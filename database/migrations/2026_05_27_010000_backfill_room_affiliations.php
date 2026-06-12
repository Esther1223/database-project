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
        $fallbackAffiliationId = DB::table('Affiliation')
            ->where('name', '總務處')
            ->value('id')
            ?? DB::table('Affiliation')->orderBy('id')->value('id');

        if ($fallbackAffiliationId === null) {
            return;
        }

        DB::table('Room')
            ->whereNull('affiliation_id')
            ->update(['affiliation_id' => $fallbackAffiliationId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
