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
        Schema::table('Reservation', function (Blueprint $table): void {
            if (! Schema::hasColumn('Reservation', 'reservation_group_id')) {
                $table->uuid('reservation_group_id')->nullable()->after('id');
            }

            if (! Schema::hasColumn('Reservation', 'reservation_date')) {
                $table->date('reservation_date')->nullable()->after('room_id');
            }

            if (! Schema::hasColumn('Reservation', 'time_slot_id')) {
                $table->unsignedBigInteger('time_slot_id')->nullable()->after('reservation_date');
            }
        });

        if (Schema::hasColumn('Reservation', 'start_time')) {
            DB::table('Reservation')
                ->select(['id', 'room_id', 'start_time'])
                ->whereNull('reservation_date')
                ->orderBy('id')
                ->get()
                ->each(function ($reservation): void {
                    $startTime = Carbon::parse($reservation->start_time);
                    $timeSlotId = DB::table('Time_slot')
                        ->where('room_id', $reservation->room_id)
                        ->where('period', $startTime->hour)
                        ->value('id');

                    DB::table('Reservation')
                        ->where('id', $reservation->id)
                        ->update([
                            'reservation_group_id' => (string) Str::uuid(),
                            'reservation_date' => $startTime->toDateString(),
                            'time_slot_id' => $timeSlotId,
                        ]);
                });
        }
    }

    public function down(): void
    {
        Schema::table('Reservation', function (Blueprint $table): void {
            $dropColumns = collect(['reservation_group_id', 'reservation_date', 'time_slot_id'])
                ->filter(fn (string $column): bool => Schema::hasColumn('Reservation', $column))
                ->all();

            if ($dropColumns !== []) {
                $table->dropColumn($dropColumns);
            }
        });
    }
};
