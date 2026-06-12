<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasColumn('reservations', 'payment_status') && DB::getDriverName() === 'mysql') {
            DB::statement("ALTER TABLE reservations MODIFY payment_status VARCHAR(50) NOT NULL DEFAULT 'unpaid'");
        }
    }

    public function down(): void
    {
        //
    }
};
