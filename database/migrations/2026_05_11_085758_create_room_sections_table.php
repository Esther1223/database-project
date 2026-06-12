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
        Schema::create('Reserve_timeslot', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('Reservation')->cascadeOnDelete();
            $table->foreignId('time_slot_id')->constrained('Time_slot')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['reservation_id', 'time_slot_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Reserve_timeslot');
    }
};
