<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('Room', function (Blueprint $table): void {
            $table->boolean('open_access_all')->default(false)->after('is_open_access');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Room', function (Blueprint $table): void {
            $table->dropColumn('open_access_all');
        });
    }
};