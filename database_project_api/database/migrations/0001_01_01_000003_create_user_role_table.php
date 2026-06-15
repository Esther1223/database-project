<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('User_Role', function (Blueprint $table): void {
            $table->foreignId('user_id')->constrained('User')->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('Role')->cascadeOnDelete();
            $table->primary(['user_id', 'role_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('User_Role');
    }
};
