<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Afflication;
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

        $afflications = collect([
            '資訊工程學系',
            '資訊中心',
            '總務處',
            '數學系',
            '光電工程學系',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Afflication::updateOrCreate(['name' => $name]),
        ]);

        $usersData = [
            ['role' => '管理員', 'name' => 'Admin User', 'email' => 'admin@example.com', 'password' => 'admin', 'affiliation' => '資訊中心'],
            ['role' => '行政人員', 'name' => 'Staff User', 'email' => 'staff@example.com', 'password' => 'staff', 'affiliation' => '總務處'],
            ['role' => '教授', 'name' => 'JhihChaing Wu', 'email' => 'wu@example.com', 'password' => 'wu', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => 'KaBuo', 'email' => 'KaBuo@example.com', 'password' => 'kabuo', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => 'Omuba', 'email' => 'Omuba@example.com', 'password' => 'omuba', 'affiliation' => '光電工程學系'],
        ];

        $users = collect($usersData)->mapWithKeys(function (array $u) use ($roles, $afflications): array {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'affiliation' => $u['affiliation'],
                    'afflication_id' => $afflications[$u['affiliation']]->id ?? null,
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
            ['room_name' => 'A101 會議室', 'room_type' => '會議室', 'capacity' => 30, 'building' => '科教大樓', 'afflication_id' => $afflications['資訊工程學系']->id, 'information' => '含投影機、白板。', 'hourly_rate' => 500, 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B201 教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '教學大樓', 'afflication_id' => $afflications['資訊中心']->id, 'information' => '適合課程與講座。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => false],
            ['room_name' => 'C301 多功能廳', 'room_type' => '多功能廳', 'capacity' => 120, 'building' => '活動中心', 'afflication_id' => $afflications['總務處']->id, 'information' => '大型活動、表演。', 'hourly_rate' => 1500, 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B1 多功能教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '科教大樓', 'afflication_id' => $afflications['資訊工程學系']->id, 'information' => '開放式空間，任何人都可以借用。', 'hourly_rate' => 0, 'need_approval' => false, 'is_open_access' => true, 'open_access_all' => true],
        ];

        $rooms = collect($roomsData)->mapWithKeys(fn (array $r): array => [
            $r['room_name'] => Room::updateOrCreate(['room_name' => $r['room_name']], $r),
        ]);

        $rooms['B1 多功能教室']->openAfflications()->syncWithoutDetaching([
            $afflications['光電工程學系']->id,
        ]);

        $timeSlots = $rooms->mapWithKeys(fn (Room $room, string $roomName): array => [
            $roomName => collect(range(8, 21))->mapWithKeys(fn (int $period): array => [
                $period => TimeSlot::updateOrCreate(
                    ['room_id' => $room->id, 'period' => $period],
                    ['price' => (int) $room->hourly_rate],
                ),
            ]),
        ]);

        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $dayAfter = Carbon::today()->addDays(2);

        $createSectionsForDate = function (Room $room, string $roomName, Carbon $date) use ($timeSlots): void {
            foreach ($timeSlots[$roomName] as $timeSlot) {
                RoomSection::updateOrCreate(
                    ['room_id' => $room->id, 'date' => $date->toDateString(), 'time_slot_id' => $timeSlot->id],
                    ['status' => 'available'],
                );
            }
        };

        foreach ($rooms as $roomName => $room) {
            $createSectionsForDate($room, $roomName, $today);
            $createSectionsForDate($room, $roomName, $tomorrow);
            $createSectionsForDate($room, $roomName, $dayAfter);
        }

        $singleDate = $today->toDateString();
        Reservation::query()
            ->where('room_id', $rooms['B201 教室']->id)
            ->whereDate('reservation_date', $singleDate)
            ->where('time_slot_id', $timeSlots['B201 教室'][13]->id)
            ->delete();

        RoomSection::updateOrCreate(
            ['room_id' => $rooms['B201 教室']->id, 'date' => $singleDate, 'time_slot_id' => $timeSlots['B201 教室'][13]->id],
            ['status' => 'available'],
        );

        $multiDate = $tomorrow->toDateString();
        Reservation::query()
            ->where('user_id', $professor->id)
            ->where('room_id', $rooms['A101 會議室']->id)
            ->whereDate('reservation_date', $multiDate)
            ->whereIn('time_slot_id', collect([9, 10, 11])->map(fn (int $period): int => $timeSlots['A101 會議室'][$period]->id))
            ->delete();

        $multiGroupId = (string) Str::uuid();
        $multiReservations = collect([9, 10, 11])->map(function (int $period) use ($multiDate, $multiGroupId, $rooms, $professor, $timeSlots): Reservation {
            return Reservation::updateOrCreate(
                [
                    'user_id' => $professor->id,
                    'room_id' => $rooms['A101 會議室']->id,
                    'reservation_date' => $multiDate,
                    'time_slot_id' => $timeSlots['A101 會議室'][$period]->id,
                ],
                [
                    'reservation_group_id' => $multiGroupId,
                    'reservation_status' => 'success',
                ],
            );
        });

        foreach ([9, 10, 11] as $period) {
            RoomSection::updateOrCreate(
                ['room_id' => $rooms['A101 會議室']->id, 'date' => $multiDate, 'time_slot_id' => $timeSlots['A101 會議室'][$period]->id],
                ['status' => 'reserved'],
            );
        }

        $firstMultiRes = $multiReservations->first();

        if ($firstMultiRes !== null) {
            $amount = $multiReservations->sum(fn (Reservation $reservation): int => (int) $reservation->timeSlot?->price);

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

        Reservation::updateOrCreate(
            [
                'user_id' => $admin->id,
                'room_id' => $rooms['C301 多功能廳']->id,
                'reservation_date' => $cancelDate,
                'time_slot_id' => $timeSlots['C301 多功能廳'][10]->id,
            ],
            [
                'reservation_group_id' => (string) Str::uuid(),
                'reservation_status' => 'cancelled',
            ],
        );

        Reservation::updateOrCreate(
            [
                'user_id' => $studentB->id,
                'room_id' => $rooms['A101 會議室']->id,
                'reservation_date' => $multiDate,
                'time_slot_id' => $timeSlots['A101 會議室'][14]->id,
            ],
            [
                'reservation_group_id' => (string) Str::uuid(),
                'reservation_status' => 'pending',
            ],
        );
    }
}
