<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacySchema = Schema::hasColumn('Time_slot', 'time_slot_id');

        if ($legacySchema) {
            $rooms = DB::table('Room')
                ->get(Schema::hasColumn('Room', 'hourly_rate') ? ['id', 'hourly_rate'] : ['id']);

            DB::table('Time_slot')->delete();

            Schema::table('Time_slot', function (Blueprint $table): void {
                $table->dropUnique(['time_slot_id']);
                $table->dropColumn('time_slot_id');
                $table->dropColumn('status');
                $table->foreignId('room_id')->after('id')->constrained('Room')->cascadeOnDelete();
                $table->unsignedTinyInteger('period')->after('room_id');
                $table->unsignedInteger('price')->default(0)->after('period');
                $table->unique(['room_id', 'period']);
            });

            foreach ($rooms as $room) {
                foreach (range(8, 21) as $period) {
                    DB::table('Time_slot')->insert([
                        'room_id' => $room->id,
                        'period' => $period,
                        'price' => (int) ($room->hourly_rate ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->remapSlotForeignKeys();

            DB::statement('ALTER TABLE Reservation MODIFY time_slot_id BIGINT UNSIGNED NULL');
        }

        Schema::table('Reservation', function (Blueprint $table): void {
            if (Schema::hasColumn('Reservation', 'start_time')) {
                $table->dropColumn('start_time');
            }

            if (Schema::hasColumn('Reservation', 'end_time')) {
                $table->dropColumn('end_time');
            }

            $table->foreign('time_slot_id')->references('id')->on('Time_slot')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('Reservation', function (Blueprint $table): void {
            $table->dropForeign(['time_slot_id']);
            $table->dateTime('start_time')->nullable()->after('time_slot_id');
            $table->dateTime('end_time')->nullable()->after('start_time');
        });

    }

    private function remapSlotForeignKeys(): void
    {
        foreach (DB::table('Reservation')->get(['id', 'room_id', 'time_slot_id', 'start_time']) as $reservation) {
            $period = $reservation->time_slot_id
                ? $this->legacyPeriod((string) $reservation->time_slot_id)
                : (int) date('G', strtotime((string) $reservation->start_time));
            $newSlotId = DB::table('Time_slot')
                ->where('room_id', $reservation->room_id)
                ->where('period', $period)
                ->value('id');

            DB::table('Reservation')->where('id', $reservation->id)->update(['time_slot_id' => $newSlotId]);
        }
    }

    private function legacyPeriod(string $legacySlotId): int
    {
        if (ctype_digit($legacySlotId)) {
            return (int) $legacySlotId;
        }

        if (preg_match('/^TS_(\d{2})00$/', $legacySlotId, $matches) === 1) {
            return 8 + (int) $matches[1];
        }

        return 8;
    }
};
