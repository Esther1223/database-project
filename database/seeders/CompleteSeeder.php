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
use Carbon\Carbon;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class CompleteSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        // Roles
        $roles = collect([
            '管理員',
            '行政人員',
            '教授',
            '學生',
        ])->mapWithKeys(fn (string $roleType): array => [
            $roleType => Role::updateOrCreate(['role_type' => $roleType]),
        ]);

        // Departments
        $departments = collect([
            '資訊工程學系',
            '資訊中心',
            '總務處',
            '數學系',
            '光電工程學系',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Department::updateOrCreate(['name' => $name]),
        ]);

        // Users
        $usersData = [
            ['role' => '管理員', 'name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'admin', 'affiliation' => '資訊中心'],
            ['role' => '行政人員', 'name' => 'Staff User', 'email' => 'staff@example.com', 'password' => 'staff', 'affiliation' => '總務處'],
            ['role' => '教授', 'name' => 'JhihChaing Wu', 'email' => 'wu@example.com', 'password' => 'wu', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => 'KaBuo', 'email' => 'KaBuo@example.com', 'password' => 'kabuo', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => 'Omuba', 'email' => 'Omuba@example.com', 'password' => 'omuba', 'affiliation' => '光電工程學系'],
        ];

        $users = collect($usersData)->mapWithKeys(function (array $u) use ($roles, $departments): array {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'affiliation' => $u['affiliation'],
                    'department_id' => $departments[$u['affiliation']]->id ?? null,
                ],
            );

            $user->roles()->syncWithoutDetaching([$roles[$u['role']]->id]);

            // key by email to avoid duplicate-role overwrite
            return [$u['email'] => $user];
        });

        // helper variables for clarity
        $admin = $users['admin@example.com'];
        $staff = $users['staff@example.com'];
        $professor = $users['wu@example.com'];
        $studentA = $users['KaBuo@example.com'];
        $studentB = $users['Omuba@example.com'];

        // Rooms
        $roomsData = [
            ['room_name' => 'A101 會議室', 'room_type' => '會議室', 'capacity' => 30, 'building' => '科教大樓', 'department_id' => $departments['資訊工程學系']->id, 'information' => '含投影機、白板。', 'hourly_rate' => 500, 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B201 教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '教學大樓', 'department_id' => null, 'information' => '適合課程與講座。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => false],
            ['room_name' => 'C301 多功能廳', 'room_type' => '多功能廳', 'capacity' => 120, 'building' => '活動中心', 'department_id' => null, 'information' => '大型活動、表演。', 'hourly_rate' => 1500, 'need_approval' => true, 'is_open_access' => false],
            // B1 is open-access across departments — associate with 光電工程學系 as requested
            // B1 is owned by 資訊工程學系 but open to other departments (e.g. 光電工程學系)
            ['room_name' => 'B1 多功能教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '科教大樓', 'department_id' => $departments['資訊工程學系']->id, 'information' => '開放式空間，資工系擁有，光電系可借用。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => true],
        ];

        $rooms = collect($roomsData)->mapWithKeys(fn (array $r): array => [
            $r['room_name'] => Room::updateOrCreate(['room_name' => $r['room_name']], $r),
        ]);

        // Make B1 available to 光電工程學系 as an open department
        if (isset($rooms['B1 多功能教室']) && isset($departments['光電工程學系'])) {
            $rooms['B1 多功能教室']->openDepartments()->syncWithoutDetaching([$departments['光電工程學系']->id]);
        }

        // Time slots (8..20)
        $timeSlots = collect(range(8, 20))->mapWithKeys(fn (int $h): array => [
            (string) $h => TimeSlot::updateOrCreate(['time_slot_id' => (string) $h], ['status' => 'enable']),
        ]);

        // Prepare dates
        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $dayAfter = Carbon::today()->addDays(2);

        // Helper to create room sections for a date
        $createSectionsForDate = function (Room $room, Carbon $date) use ($timeSlots) {
            foreach ($timeSlots as $slotId => $ts) {
                RoomSection::updateOrCreate(
                    ['room_id' => $room->id, 'date' => $date->toDateString(), 'time_slot_id' => $slotId],
                    ['status' => 'available'],
                );
            }
        };

        // Create sections for rooms for next 3 days
        foreach ($rooms as $room) {
            $createSectionsForDate($room, $today);
            $createSectionsForDate($room, $tomorrow);
            $createSectionsForDate($room, $dayAfter);
        }

        // Create a multi-slot reservation (教授) spanning 09:00-12:00 tomorrow (slots 9,10,11)
        $multiDate = $tomorrow->toDateString();
        $multiStart = "{$multiDate} 09:00:00";
        $multiEnd = "{$multiDate} 12:00:00";

        $multiRes = Reservation::updateOrCreate(
            [
                'user_id' => $professor->id,
                'room_id' => $rooms['A101 會議室']->id,
                'start_time' => $multiStart,
                'end_time' => $multiEnd,
            ],
            ['reservation_status' => 'success'],
        );

        // mark sections reserved
        foreach (['9', '10', '11'] as $slotId) {
            RoomSection::updateOrCreate(
                ['room_id' => $rooms['A101 會議室']->id, 'date' => $multiDate, 'time_slot_id' => $slotId],
                ['status' => 'reserved'],
            );
        }

        // Payment for multi reservation
        $hours = 3;
        $amount = $rooms['A101 會議室']->hourly_rate * $hours;
        if ($amount > 0) {
            Payment::updateOrCreate(
                ['reservation_id' => $multiRes->id],
                ['amount' => $amount, 'payment_status' => 'unpaid'],
            );
        }

        // Single-slot reservation (學生) today 13:00-14:00 (slot 13)
        $singleDate = $today->toDateString();
        $singleStart = "{$singleDate} 13:00:00";
        $singleEnd = "{$singleDate} 14:00:00";

        // use student A for the single-slot reservation
        $singleRes = Reservation::updateOrCreate(
            [
                'user_id' => $studentA->id,
                'room_id' => $rooms['B201 教室']->id,
                'start_time' => $singleStart,
                'end_time' => $singleEnd,
            ],
            ['reservation_status' => 'success'],
        );

        RoomSection::updateOrCreate(
            ['room_id' => $rooms['B201 教室']->id, 'date' => $singleDate, 'time_slot_id' => '13'],
            ['status' => 'reserved'],
        );

        // Cancelled reservation (admin) day after tomorrow 10:00-11:00
        $cancelDate = $dayAfter->toDateString();
        $cancelStart = "{$cancelDate} 10:00:00";
        $cancelEnd = "{$cancelDate} 11:00:00";

        $cancelRes = Reservation::updateOrCreate(
            [
                'user_id' => $admin->id,
                'room_id' => $rooms['C301 多功能廳']->id,
                'start_time' => $cancelStart,
                'end_time' => $cancelEnd,
            ],
            ['reservation_status' => 'cancelled'],
        );

        // Pending reservation requiring approval (學生) tomorrow 14:00-15:00 for A101
        $pendingStart = "{$multiDate} 14:00:00";
        $pendingEnd = "{$multiDate} 15:00:00";

        // use student B for pending reservation
        $pendingRes = Reservation::updateOrCreate(
            [
                'user_id' => $studentB->id,
                'room_id' => $rooms['A101 會議室']->id,
                'start_time' => $pendingStart,
                'end_time' => $pendingEnd,
            ],
            ['reservation_status' => 'pending'],
        );

        // Create an approval for the multi reservation (approved)
        Approval::updateOrCreate(
            ['reservation_id' => $multiRes->id],
            ['approver_id' => $staff->id, 'decision' => 'approved', 'decision_time' => Carbon::now()->subDays(1)->toDateTimeString()],
        );

        // Create a paid payment for singleRes
        Payment::updateOrCreate(
            ['reservation_id' => $singleRes->id],
            ['amount' => 0, 'payment_status' => 'paid'],
        );
    }
}
