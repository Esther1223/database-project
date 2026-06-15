<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Reservation', function (Blueprint $table): void {
            $table->id();
            $table->uuid('reservation_group_id')->nullable();
            $table->foreignId('user_id')->constrained('User')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('Room')->cascadeOnDelete();
            $table->date('reservation_date')->nullable();
            $table->foreignId('time_slot_id')->nullable()->constrained('Time_slot')->nullOnDelete();
            $table->string('reservation_status');
            $table->string('payment_status')->default('unpaid');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Reservation');
    }
};
