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
        if (Schema::hasColumn('Room', 'afflication_id') && ! Schema::hasColumn('Room', 'affiliation_id')) {
            Schema::table('Room', function (Blueprint $table) {
                $table->renameColumn('afflication_id', 'affiliation_id');
            });
        }

        Schema::table('Room', function (Blueprint $table) {
            if (! Schema::hasColumn('Room', 'affiliation_id')) {
                $table->foreignId('affiliation_id')->nullable()->after('building')
                    ->constrained('Affiliation')
                    ->nullOnDelete();
            }

            if (! Schema::hasColumn('Room', 'is_open_access')) {
                $table->boolean('is_open_access')->default(false)->after('need_approval');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('Room', function (Blueprint $table) {
            $table->dropColumn('is_open_access');
            $table->dropConstrainedForeignId('affiliation_id');
        });
    }
};
