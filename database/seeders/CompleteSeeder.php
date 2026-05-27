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
use Illuminate\Support\Str;

class CompleteSeeder extends Seeder
{
    use WithoutModelEvents;

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
            '數學系',
            '光電工程學系',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Department::updateOrCreate(['name' => $name]),
        ]);

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

            return [$u['email'] => $user];
        });

        $admin = $users['admin@example.com'];
        $staff = $users['staff@example.com'];
        $professor = $users['wu@example.com'];
        $studentB = $users['Omuba@example.com'];

        $roomsData = [
            ['room_name' => 'A101 會議室', 'room_type' => '會議室', 'capacity' => 30, 'building' => '科教大樓', 'department_id' => $departments['資訊工程學系']->id, 'information' => '含投影機、白板。', 'hourly_rate' => 500, 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B201 教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '教學大樓', 'department_id' => $departments['資訊中心']->id, 'information' => '適合課程與講座。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => false],
            ['room_name' => 'C301 多功能廳', 'room_type' => '多功能廳', 'capacity' => 120, 'building' => '活動中心', 'department_id' => $departments['總務處']->id, 'information' => '大型活動、表演。', 'hourly_rate' => 1500, 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B1 多功能教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '科教大樓', 'department_id' => $departments['資訊工程學系']->id, 'information' => '開放式空間，資工系擁有，光電系可借用。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => true],
        ];

        $rooms = collect($roomsData)->mapWithKeys(fn (array $r): array => [
            $r['room_name'] => Room::updateOrCreate(['room_name' => $r['room_name']], $r),
        ]);

        $rooms['B1 多功能教室']->openDepartments()->syncWithoutDetaching([
            $departments['光電工程學系']->id,
        ]);

        $timeSlots = collect(range(8, 20))->mapWithKeys(fn (int $h): array => [
            (string) $h => TimeSlot::updateOrCreate(['time_slot_id' => (string) $h], ['status' => 'enable']),
        ]);

        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $dayAfter = Carbon::today()->addDays(2);

        $createSectionsForDate = function (Room $room, Carbon $date) use ($timeSlots): void {
            foreach ($timeSlots as $slotId => $ts) {
                RoomSection::updateOrCreate(
                    ['room_id' => $room->id, 'date' => $date->toDateString(), 'time_slot_id' => $slotId],
                    ['status' => 'available'],
                );
            }
        };

        foreach ($rooms as $room) {
            $createSectionsForDate($room, $today);
            $createSectionsForDate($room, $tomorrow);
            $createSectionsForDate($room, $dayAfter);
        }

        $singleDate = $today->toDateString();
        Reservation::query()
            ->where('room_id', $rooms['B201 教室']->id)
            ->where('start_time', "{$singleDate} 13:00:00")
            ->where('end_time', "{$singleDate} 14:00:00")
            ->delete();

        RoomSection::updateOrCreate(
            ['room_id' => $rooms['B201 教室']->id, 'date' => $singleDate, 'time_slot_id' => '13'],
            ['status' => 'available'],
        );

        $multiDate = $tomorrow->toDateString();
        Reservation::query()
            ->where('user_id', $professor->id)
            ->where('room_id', $rooms['A101 會議室']->id)
            ->where('start_time', "{$multiDate} 09:00:00")
            ->where('end_time', "{$multiDate} 12:00:00")
            ->delete();

        $multiGroupId = (string) Str::uuid();
        $multiReservations = collect(['9', '10', '11'])->map(function (string $slotId) use ($multiDate, $multiGroupId, $rooms, $professor): Reservation {
            $startHour = (int) $slotId;

            return Reservation::updateOrCreate(
                [
                    'user_id' => $professor->id,
                    'room_id' => $rooms['A101 會議室']->id,
                    'reservation_date' => $multiDate,
                    'time_slot_id' => $slotId,
                    'start_time' => sprintf('%s %02d:00:00', $multiDate, $startHour),
                    'end_time' => sprintf('%s %02d:00:00', $multiDate, $startHour + 1),
                ],
                [
                    'reservation_group_id' => $multiGroupId,
                    'reservation_status' => 'success',
                ],
            );
        });

        foreach (['9', '10', '11'] as $slotId) {
            RoomSection::updateOrCreate(
                ['room_id' => $rooms['A101 會議室']->id, 'date' => $multiDate, 'time_slot_id' => $slotId],
                ['status' => 'reserved'],
            );
        }

        $firstMultiRes = $multiReservations->first();

        if ($firstMultiRes !== null) {
            $amount = $rooms['A101 會議室']->hourly_rate * $multiReservations->count();

            if ($amount > 0) {
                Payment::updateOrCreate(
                    ['reservation_id' => $firstMultiRes->id],
                    ['amount' => $amount, 'payment_status' => 'unpaid'],
                );

                Payment::whereIn('reservation_id', $multiReservations->pluck('id')->filter()->values())
                    ->where('reservation_id', '!=', $firstMultiRes->id)
                    ->delete();
            }

            Approval::updateOrCreate(
                ['reservation_id' => $firstMultiRes->id],
                ['approver_id' => $staff->id, 'decision' => 'approved', 'decision_time' => Carbon::now()->subDays(1)->toDateTimeString()],
            );
        }

        $cancelDate = $dayAfter->toDateString();
        $cancelStart = "{$cancelDate} 10:00:00";
        $cancelEnd = "{$cancelDate} 11:00:00";

        Reservation::updateOrCreate(
            [
                'user_id' => $admin->id,
                'room_id' => $rooms['C301 多功能廳']->id,
                'reservation_date' => $cancelDate,
                'time_slot_id' => '10',
                'start_time' => $cancelStart,
                'end_time' => $cancelEnd,
            ],
            [
                'reservation_group_id' => (string) Str::uuid(),
                'reservation_status' => 'cancelled',
            ],
        );

        $pendingStart = "{$multiDate} 14:00:00";
        $pendingEnd = "{$multiDate} 15:00:00";

        Reservation::updateOrCreate(
            [
                'user_id' => $studentB->id,
                'room_id' => $rooms['A101 會議室']->id,
                'reservation_date' => $multiDate,
                'time_slot_id' => '14',
                'start_time' => $pendingStart,
                'end_time' => $pendingEnd,
            ],
            [
                'reservation_group_id' => (string) Str::uuid(),
                'reservation_status' => 'pending',
            ],
        );
    }
}
