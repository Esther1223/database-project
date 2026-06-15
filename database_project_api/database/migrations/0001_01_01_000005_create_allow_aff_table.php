<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Allow_aff', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('affiliation_id')->constrained('Affiliation')->cascadeOnDelete();
            $table->foreignId('room_id')->constrained('Room')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['affiliation_id', 'room_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Allow_aff');
    }
};
