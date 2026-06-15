<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('Room', function (Blueprint $table): void {
            $table->id();
            $table->string('room_name');
            $table->string('room_type');
            $table->integer('capacity');
            $table->string('building');
            $table->foreignId('affiliation_id')->nullable()->constrained('Affiliation')->nullOnDelete();
            $table->text('information')->nullable();
            $table->boolean('need_approval')->default(false);
            $table->boolean('is_open_access')->default(false);
            $table->boolean('open_access_all')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('Room');
    }
};
