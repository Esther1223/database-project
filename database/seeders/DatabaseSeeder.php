<?php

namespace Database\Seeders;

use App\Models\Approval;
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
        ])->mapWithKeys(function (array $userData) use ($roles): array {
            $user = User::updateOrCreate(
                ['email' => $userData['email']],
                [
                    'name' => $userData['name'],
                    'password' => Hash::make($userData['password']),
                    'affiliation' => $userData['affiliation'],
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
                'information' => '含投影機、白板與視訊設備。',
                'hourly_rate' => 500,
                'need_approval' => true,
            ],
            [
                'room_name' => 'B201 教室',
                'room_type' => '教室',
                'capacity' => 60,
                'building' => '教學大樓',
                'information' => '適合課程、講座與社團活動。',
                'hourly_rate' => 0,
                'need_approval' => false,
            ],
        ])->mapWithKeys(fn (array $roomData): array => [
            $roomData['room_name'] => Room::updateOrCreate(
                ['room_name' => $roomData['room_name']],
                $roomData,
            ),
        ]);

        $timeSlots = collect([
            ['time_slot_id' => 'TS_0000',  'status' => 'enable'],
            ['time_slot_id' => 'TS_0100',  'status' => 'enable'],
            ['time_slot_id' => 'TS_0200', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0300', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0400', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0500', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0600', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0700', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0800', 'status' => 'enable'],
            ['time_slot_id' => 'TS_0900', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1000', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1100', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1200', 'status' => 'disable'],
            ['time_slot_id' => 'TS_1300', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1400', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1500', 'status' => 'enable'],
            ['time_slot_id' => 'TS_1600', 'status' => 'enable'],
        ])->mapWithKeys(fn (array $timeSlotData): array => [
            $timeSlotData['time_slot_id'] => TimeSlot::updateOrCreate(
                ['time_slot_id' => $timeSlotData['time_slot_id']],
                $timeSlotData,
            ),
        ]);

        foreach ($rooms as $room) {
            foreach (['2026-05-12', '2026-05-13', '2026-05-14'] as $date) {
                foreach ($timeSlots as $timeSlot) {
                    RoomSection::updateOrCreate(
                        [
                            'room_id' => $room->id,
                            'date' => $date,
                            'time_slot_id' => $timeSlot->time_slot_id,
                        ],
                        [
                            'status' => $timeSlot->status === 'disable' ? 'unavailable' : 'available',
                        ],
                    );
                }
            }
        }

        $reservation = Reservation::updateOrCreate(
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
            ->whereIn('time_slot_id', ['TS_0900', 'TS_1000'])
            ->update(['status' => 'reserved']);

        Approval::updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'approver_id' => $users['行政人員']->id,
                'decision' => 'approved',
                'decision_time' => '2026-05-11 10:00:00',
            ],
        );

        Payment::updateOrCreate(
            ['reservation_id' => $reservation->id],
            [
                'amount' => 1000,
                'payment_status' => 'paid',
            ],
        );
    }
}