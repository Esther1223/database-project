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
        $fallbackAfflicationId = DB::table('afflications')
            ->where('name', '總務處')
            ->value('id')
            ?? DB::table('afflications')->orderBy('id')->value('id');

        if ($fallbackAfflicationId === null) {
            return;
        }

        DB::table('rooms')
            ->whereNull('afflication_id')
            ->update(['afflication_id' => $fallbackAfflicationId]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
