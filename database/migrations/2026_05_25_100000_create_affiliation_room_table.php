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
        if (Schema::hasTable('afflication_room') && ! Schema::hasTable('affiliation_room')) {
            Schema::rename('afflication_room', 'affiliation_room');
        }

        if (Schema::hasTable('affiliation_room')) {
            if (Schema::hasColumn('affiliation_room', 'afflication_id') && ! Schema::hasColumn('affiliation_room', 'affiliation_id')) {
                Schema::table('affiliation_room', function (Blueprint $table) {
                    $table->renameColumn('afflication_id', 'affiliation_id');
                });
            }

            return;
        }

        Schema::create('affiliation_room', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliation_id')->constrained()->cascadeOnDelete();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('affiliation_room');
    }
};
