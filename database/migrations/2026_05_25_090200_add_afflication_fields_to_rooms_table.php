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
        Schema::table('rooms', function (Blueprint $table) {
            $table->foreignId('afflication_id')->nullable()->after('building')
                ->constrained('afflications')
                ->nullOnDelete();
            $table->boolean('is_open_access')->default(false)->after('need_approval');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('is_open_access');
            $table->dropConstrainedForeignId('afflication_id');
        });
    }
};
