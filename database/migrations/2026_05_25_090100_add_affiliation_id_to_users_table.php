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
        if (Schema::hasColumn('users', 'afflication_id') && ! Schema::hasColumn('users', 'affiliation_id')) {
            Schema::table('users', function (Blueprint $table) {
                $table->renameColumn('afflication_id', 'affiliation_id');
            });

            return;
        }

        if (Schema::hasColumn('users', 'affiliation_id')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            $table->foreignId('affiliation_id')->nullable()->after('affiliation')
                ->constrained('affiliations')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('affiliation_id');
        });
    }
};
