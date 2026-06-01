<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $legacySchema = Schema::hasColumn('time_slots', 'time_slot_id');

        if ($legacySchema) {
            $rooms = DB::table('rooms')->get(['id', 'hourly_rate']);

            Schema::table('room_sections', function (Blueprint $table): void {
                $table->dropForeign(['time_slot_id']);
            });

            DB::table('time_slots')->delete();

            Schema::table('time_slots', function (Blueprint $table): void {
                $table->dropUnique(['time_slot_id']);
                $table->dropColumn('time_slot_id');
                $table->dropColumn('status');
                $table->foreignId('room_id')->after('id')->constrained('rooms')->cascadeOnDelete();
                $table->unsignedTinyInteger('period')->after('room_id');
                $table->unsignedInteger('price')->default(0)->after('period');
                $table->unique(['room_id', 'period']);
            });

            foreach ($rooms as $room) {
                foreach (range(8, 21) as $period) {
                    DB::table('time_slots')->insert([
                        'room_id' => $room->id,
                        'period' => $period,
                        'price' => (int) ($room->hourly_rate ?? 0),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
            }

            $this->remapSlotForeignKeys();

            DB::statement('ALTER TABLE room_sections MODIFY time_slot_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE reservations MODIFY time_slot_id BIGINT UNSIGNED NULL');
        }

        if ($legacySchema) {
            Schema::table('room_sections', function (Blueprint $table): void {
                $table->foreign('time_slot_id')->references('id')->on('time_slots')->cascadeOnDelete();
            });
        }

        Schema::table('reservations', function (Blueprint $table): void {
            if (Schema::hasColumn('reservations', 'start_time')) {
                $table->dropColumn('start_time');
            }

            if (Schema::hasColumn('reservations', 'end_time')) {
                $table->dropColumn('end_time');
            }

            $table->foreign('time_slot_id')->references('id')->on('time_slots')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropForeign(['time_slot_id']);
            $table->dateTime('start_time')->nullable()->after('time_slot_id');
            $table->dateTime('end_time')->nullable()->after('start_time');
        });

        Schema::table('room_sections', function (Blueprint $table): void {
            $table->dropForeign(['time_slot_id']);
        });
    }

    private function remapSlotForeignKeys(): void
    {
        foreach (DB::table('room_sections')->get(['id', 'room_id', 'time_slot_id']) as $section) {
            $newSlotId = DB::table('time_slots')
                ->where('room_id', $section->room_id)
                ->where('period', $this->legacyPeriod((string) $section->time_slot_id))
                ->value('id');

            DB::table('room_sections')->where('id', $section->id)->update(['time_slot_id' => $newSlotId]);
        }

        foreach (DB::table('reservations')->get(['id', 'room_id', 'time_slot_id', 'start_time']) as $reservation) {
            $period = $reservation->time_slot_id
                ? $this->legacyPeriod((string) $reservation->time_slot_id)
                : (int) date('G', strtotime((string) $reservation->start_time));
            $newSlotId = DB::table('time_slots')
                ->where('room_id', $reservation->room_id)
                ->where('period', $period)
                ->value('id');

            DB::table('reservations')->where('id', $reservation->id)->update(['time_slot_id' => $newSlotId]);
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
