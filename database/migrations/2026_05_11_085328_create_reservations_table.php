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
        Schema::create('Reservation', function (Blueprint $table) {
            $table->id();
            $table->uuid('reservation_group_id')->nullable();
            $table->foreignId('user_id')->constrained('User')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('Room')->cascadeOnDelete();
            $table->date('reservation_date')->nullable();
            $table->unsignedBigInteger('time_slot_id')->nullable();
            $table->string('reservation_status');
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Reservation');
    }
};
