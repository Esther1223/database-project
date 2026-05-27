<?php

use Carbon\Carbon;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->uuid('reservation_group_id')->nullable()->after('id');
            $table->date('reservation_date')->nullable()->after('room_id');
            $table->string('time_slot_id')->nullable()->after('reservation_date');
        });

        DB::table('reservations')
            ->select(['id', 'start_time'])
            ->whereNull('reservation_date')
            ->orderBy('id')
            ->get()
            ->each(function ($reservation): void {
                $startTime = Carbon::parse($reservation->start_time);

                DB::table('reservations')
                    ->where('id', $reservation->id)
                    ->update([
                        'reservation_group_id' => (string) Str::uuid(),
                        'reservation_date' => $startTime->toDateString(),
                        'time_slot_id' => (string) $startTime->hour,
                    ]);
            });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropColumn(['reservation_group_id', 'reservation_date', 'time_slot_id']);
        });
    }
};
