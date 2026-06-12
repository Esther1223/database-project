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
        Schema::create('Time_slot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('room_id')->constrained('Room')->cascadeOnDelete();
            $table->unsignedTinyInteger('period');
            $table->unsignedInteger('price')->default(0);
            $table->timestamps();
            $table->unique(['room_id', 'period']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Time_slot');
    }
};
