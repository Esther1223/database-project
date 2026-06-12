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
        if (Schema::hasColumn('rooms', 'afflication_id') && ! Schema::hasColumn('rooms', 'affiliation_id')) {
            Schema::table('rooms', function (Blueprint $table) {
                $table->renameColumn('afflication_id', 'affiliation_id');
            });
        }

        Schema::table('rooms', function (Blueprint $table) {
            if (! Schema::hasColumn('rooms', 'affiliation_id')) {
                $table->foreignId('affiliation_id')->nullable()->after('building')
                    ->constrained('affiliations')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('rooms', 'is_open_access')) {
                $table->boolean('is_open_access')->default(false)->after('need_approval');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('rooms', function (Blueprint $table) {
            $table->dropColumn('is_open_access');
            $table->dropConstrainedForeignId('affiliation_id');
        });
    }
};
