<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Department;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $roles = collect([
            '管理員',
            '行政人員',
            '教授',
            '學生',
        ])->mapWithKeys(fn (string $roleType): array => [
            $roleType => Role::updateOrCreate(['role_type' => $roleType]),
        ]);

        $departments = collect([
            '資訊工程學系',
            '資訊中心',
            '總務處',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Department::updateOrCreate(['name' => $name]),
        ]);

        $users = collect([
            [
                'role' => '管理員',
                'name' => 'Admin User',
                'email' => 'admin@example.com',
                'password' => 'admin',
                'affiliation' => '資訊中心',
            ],
            [
                'role' => '行政人員',
                'name' => 'Staff User',
                'email' => 'staff@example.com',
                'password' => 'staff',
                'affiliation' => '總務處',
            ],
            [
                'role' => '教授',
                'name' => 'Professor User',
                'email' => 'professor@example.com',
                'password' => 'professor',
                'affiliation' => '資訊工程學系',
            ],
            [
                'role' => '學生',
                'name' => 'Student User',
                'email' => 'student@example.com',
                'password' => 'student',
                'affiliation' => '資訊工程學系',
            ],
        ])->mapWithKeys(function (array $userData) use ($roles, $departments): array {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'affiliation' => $userData['affiliation'],
                    'department_id' => $departments[$userData['affiliation']]->id ?? null,
                ],
            );

            $user->roles()->syncWithoutDetaching([$roles[$userData['role']]->id]);

            return [$userData['role'] => $user];
        });

        $rooms = collect([
            [
                'room_name' => 'A101 會議室',
                'room_type' => '會議室',
                'capacity' => 30,
                'building' => '行政大樓',
                'department_id' => $departments['資訊工程學系']->id,
                'information' => '含投影機、白板與視訊設備。',
                'hourly_rate' => 500,
                'need_approval' => true,
                'is_open_access' => false,
            ],
            [
                'room_name' => 'B201 教室',
                'room_type' => '教室',
                'capacity' => 60,
                'building' => '教學大樓',
                'department_id' => null,
                'information' => '適合課程、講座與社團活動。',
                'hourly_rate' => 0,
                'need_approval' => false,
                'is_open_access' => false,
            ],
        ])->mapWithKeys(fn (array $roomData): array => [
            $roomData['room_name'] => Room::updateOrCreate(
                ['room_name' => $roomData['room_name']],
                $roomData,
            ),
        ]);

        $timeSlots = collect([
            ['time_slot_id' => '8', 'status' => 'enable'],
            ['time_slot_id' => '9', 'status' => 'enable'],
            ['time_slot_id' => '10', 'status' => 'enable'],
            ['time_slot_id' => '11', 'status' => 'enable'],
            ['time_slot_id' => '12', 'status' => 'enable'],
            ['time_slot_id' => '13', 'status' => 'enable'],
            ['time_slot_id' => '14', 'status' => 'enable'],
            ['time_slot_id' => '15', 'status' => 'enable'],
            ['time_slot_id' => '16', 'status' => 'enable'],
            ['time_slot_id' => '17', 'status' => 'enable'],
            ['time_slot_id' => '18', 'status' => 'enable'],
            ['time_slot_id' => '19', 'status' => 'enable'],
            ['time_slot_id' => '20', 'status' => 'enable'],

        ])->mapWithKeys(fn (array $timeSlotData): array => [
            $timeSlotData['time_slot_id'] => TimeSlot::updateOrCreate(
                ['time_slot_id' => $timeSlotData['time_slot_id']],
                $timeSlotData,
            ),
        ]);

        $approvedReservation = Reservation::updateOrCreate(
            [
                'user_id' => $users['教授']->id,
                'room_id' => $rooms['A101 會議室']->id,
                'start_time' => '2026-05-12 09:00:00',
                'end_time' => '2026-05-12 11:00:00',
            ],
            ['reservation_status' => 'success'],
        );

        RoomSection::where('room_id', $rooms['A101 會議室']->id)
            ->where('date', '2026-05-12')
            ->whereIn('time_slot_id', ['9', '10'])
            ->delete();

        foreach (['9', '10'] as $timeSlotId) {
            RoomSection::updateOrCreate(
                [
                    'room_id' => $rooms['A101 會議室']->id,
                    'date' => '2026-05-12',
                    'time_slot_id' => $timeSlotId,
                ],
                [
                    'status' => 'reserved',
                ],
            );
        }

        $directReservation = Reservation::updateOrCreate(
            [
                'user_id' => $users['學生']->id,
                'room_id' => $rooms['B201 教室']->id,
                'start_time' => '2026-05-13 13:00:00',
                'end_time' => '2026-05-13 15:00:00',
            ],
            ['reservation_status' => 'success'],
        );

        RoomSection::where('room_id', $rooms['B201 教室']->id)
            ->where('date', '2026-05-13')
            ->whereIn('time_slot_id', ['13', '14'])
            ->delete();

        foreach (['13', '14'] as $timeSlotId) {
            RoomSection::updateOrCreate(
                [
                    'room_id' => $rooms['B201 教室']->id,
                    'date' => '2026-05-13',
                    'time_slot_id' => $timeSlotId,
                ],
                [
                    'status' => 'reserved',
                ],
            );
        }

        Approval::updateOrCreate(
            ['reservation_id' => $approvedReservation->id],
            [
                'approver_id' => $users['行政人員']->id,
                'decision' => 'approved',
                'decision_time' => '2026-05-11 10:00:00',
            ],
        );

        Payment::updateOrCreate(
            ['reservation_id' => $approvedReservation->id],
            [
                'amount' => 1000,
                'payment_status' => 'paid',
            ],
        );
    }
}
