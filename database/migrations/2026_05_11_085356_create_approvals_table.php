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
        Schema::create('Approve', function (Blueprint $table) {
            $table->id();
            $table->foreignId('reservation_id')->constrained('Reservation')->cascadeOnDelete();
            $table->foreignId('approver_id')->constrained('User')->cascadeOnDelete();
            $table->enum('decision', ['approved', 'rejected']);
            $table->dateTime('decision_time');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Approve');
    }
};
