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
        if (Schema::hasColumn('User', 'afflication_id') && ! Schema::hasColumn('User', 'affiliation_id')) {
            Schema::table('User', function (Blueprint $table) {
                $table->renameColumn('afflication_id', 'affiliation_id');
            });

            return;
        }

        if (Schema::hasColumn('User', 'affiliation_id')) {
            return;
        }

        Schema::table('User', function (Blueprint $table) {
            $table->foreignId('affiliation_id')->nullable()->after('password')
                ->constrained('Affiliation')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('User', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliation_id');
        });
    }
};
