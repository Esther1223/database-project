<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        if (Schema::hasTable('User_role') && ! Schema::hasTable('User_Role')) {
            Schema::rename('User_role', 'User_Role');
        }

        if (Schema::hasTable('room_sections')) {
            Schema::drop('room_sections');
        }

        if (! Schema::hasTable('Reserve_timeslot')) {
            Schema::create('Reserve_timeslot', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('reservation_id')->constrained('Reservation')->cascadeOnDelete();
                $table->foreignId('time_slot_id')->constrained('Time_slot')->cascadeOnDelete();
                $table->timestamps();
                $table->unique(['reservation_id', 'time_slot_id']);
            });
        }

        if (Schema::hasTable('Reservation') && Schema::hasColumn('Reservation', 'time_slot_id')) {
            DB::table('Reservation')
                ->select(['id', 'time_slot_id', 'created_at', 'updated_at'])
                ->whereNotNull('time_slot_id')
                ->orderBy('id')
                ->each(function (object $reservation): void {
                    DB::table('Reserve_timeslot')->updateOrInsert(
                        [
                            'reservation_id' => $reservation->id,
                            'time_slot_id' => $reservation->time_slot_id,
                        ],
                        [
                            'created_at' => $reservation->created_at ?? now(),
                            'updated_at' => $reservation->updated_at ?? now(),
                        ],
                    );
                });
        }

        if (Schema::hasTable('User')) {
            Schema::table('User', function (Blueprint $table): void {
                if (Schema::hasColumn('User', 'affiliation')) {
                    $table->dropColumn('affiliation');
                }

                if (Schema::hasColumn('User', 'remember_token')) {
                    $table->dropColumn('remember_token');
                }
            });
        }

        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        //
    }
};
