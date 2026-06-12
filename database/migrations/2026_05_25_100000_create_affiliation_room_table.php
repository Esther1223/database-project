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
        if (Schema::hasTable('afflication_room') && ! Schema::hasTable('Allow_aff')) {
            Schema::rename('afflication_room', 'Allow_aff');
        }

        if (Schema::hasTable('Allow_aff')) {
            if (Schema::hasColumn('Allow_aff', 'afflication_id') && ! Schema::hasColumn('Allow_aff', 'affiliation_id')) {
                Schema::table('Allow_aff', function (Blueprint $table) {
                    $table->renameColumn('afflication_id', 'affiliation_id');
                });
            }

            return;
        }

        Schema::create('Allow_aff', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_id')->constrained('Affiliation')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('Room')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Allow_aff');
    }
};
