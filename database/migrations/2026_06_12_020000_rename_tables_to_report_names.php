<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * @var array<string, string>
     */
    private array $renames = [
        'affiliation_room' => 'Allow_aff',
        'approvals' => 'Approve',
        'payments' => 'Payment',
        'reservations' => 'Reservation',
        'role_user' => 'User_Role',
        'User_role' => 'User_Role',
        'roles' => 'Role',
        'rooms' => 'Room',
        'time_slots' => 'Time_slot',
        'users' => 'User',
        'affiliations' => 'Affiliation',
    ];

    public function up(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach ($this->renames as $from => $to) {
            if (Schema::hasTable($from) && ! Schema::hasTable($to)) {
                Schema::rename($from, $to);
            }
        }

        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('room_sections');
        Schema::enableForeignKeyConstraints();
    }

    public function down(): void
    {
        Schema::disableForeignKeyConstraints();

        foreach (array_reverse($this->renames, true) as $from => $to) {
            if (Schema::hasTable($to) && ! Schema::hasTable($from)) {
                Schema::rename($to, $from);
            }
        }

        Schema::enableForeignKeyConstraints();
    }
};
