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
        if (Schema::hasTable('afflications') && ! Schema::hasTable('Affiliation')) {
            Schema::rename('afflications', 'Affiliation');

            return;
        }

        if (Schema::hasTable('Affiliation')) {
            return;
        }

        Schema::create('Affiliation', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('Affiliation');
    }
};
