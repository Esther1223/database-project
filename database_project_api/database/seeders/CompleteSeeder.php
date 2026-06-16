<?php

namespace Database\Seeders;

use App\Models\Approval;
use App\Models\Affiliation;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Role;
use App\Models\Room;
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

        $affiliations = collect([
            '資訊工程學系',
            '資訊中心',
            '總務處',
            '數學系',
            '光電工程學系',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Affiliation::updateOrCreate(['name' => $name]),
        ]);

        $usersData = [
            ['role' => '管理員', 'name' => 'Admin', 'email' => 'admin@example.com', 'password' => '12345678', 'affiliation' => '資訊中心'],
            ['role' => '行政人員', 'name' => '游助教', 'email' => 'you@example.com', 'password' => '12345678', 'affiliation' => '總務處'],
            ['role' => '教授', 'name' => '柯教授', 'email' => 'ko@example.com', 'password' => '12345678', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => '劉同學', 'email' => 'liu@example.com', 'password' => '12345678', 'affiliation' => '資訊工程學系'],
            ['role' => '學生', 'name' => '歐同學', 'email' => 'oi@example.com', 'password' => '12345678', 'affiliation' => '光電工程學系'],
        ];

        $users = collect($usersData)->mapWithKeys(function (array $u) use ($roles, $affiliations): array {
            $user = User::updateOrCreate(
                ['email' => $u['email']],
                [
                    'name' => $u['name'],
                    'password' => Hash::make($u['password']),
                    'affiliation_id' => $affiliations[$u['affiliation']]->id ?? null,
                ],
            );

            $user->roles()->syncWithoutDetaching([$roles[$u['role']]->id]);

            return [$u['email'] => $user];
        });

        $admin = $users['admin@example.com'];
        $staff = $users['you@example.com'];
        $professor = $users['ko@example.com'];
        $studentB = $users['liu@example.com'];
        $studentC = $users['oi@example.com'];

        $roomPrices = [
            'A101 會議室' => 500,
            'B201 教室' => 0,
            'C301 多功能廳' => 1500,
            'B1 多功能教室' => 0,
        ];

        $roomsData = [
            ['room_name' => 'A101 會議室', 'room_type' => '會議室', 'capacity' => 30, 'building' => '科教大樓', 'affiliation_id' => $affiliations['資訊工程學系']->id, 'information' => '含投影機、白板。', 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B201 教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '教學大樓', 'affiliation_id' => $affiliations['資訊中心']->id, 'information' => '適合課程與講座。', 'need_approval' => false, 'is_open_access' => false],
            ['room_name' => 'C301 多功能廳', 'room_type' => '多功能廳', 'capacity' => 120, 'building' => '活動中心', 'affiliation_id' => $affiliations['總務處']->id, 'information' => '大型活動、表演。', 'need_approval' => true, 'is_open_access' => false],
            ['room_name' => 'B1 多功能教室', 'room_type' => '教室', 'capacity' => 60, 'building' => '科教大樓', 'affiliation_id' => $affiliations['資訊工程學系']->id, 'information' => '開放式空間，任何人都可以借用。', 'need_approval' => false, 'is_open_access' => true, 'open_access_all' => true],
        ];

        $rooms = collect($roomsData)->mapWithKeys(fn (array $r): array => [
            $r['room_name'] => Room::updateOrCreate(['room_name' => $r['room_name']], $r),
        ]);

        $rooms['B1 多功能教室']->openAffiliations()->syncWithoutDetaching([
            $affiliations['光電工程學系']->id,
        ]);

        $timeSlots = $rooms->mapWithKeys(fn (Room $room, string $roomName): array => [
            $roomName => collect(range(8, 21))->mapWithKeys(fn (int $period): array => [
                $period => TimeSlot::updateOrCreate(
                    ['room_id' => $room->id, 'period' => $period],
                    ['price' => (int) ($roomPrices[$roomName] ?? 0)],
                ),
            ]),
        ]);

        $today = Carbon::today();
        $tomorrow = Carbon::today()->addDay();
        $dayAfter = Carbon::today()->addDays(2);

        $singleDate = $today->toDateString();
        Reservation::query()
            ->where('room_id', $rooms['B201 教室']->id)
            ->whereDate('reservation_date', $singleDate)
            ->where('time_slot_id', $timeSlots['B201 教室'][13]->id)
            ->delete();

        $multiDate = $tomorrow->toDateString();
        Reservation::query()
            ->where('user_id', $professor->id)
            ->where('room_id', $rooms['A101 會議室']->id)
            ->whereDate('reservation_date', $multiDate)
            ->whereIn('time_slot_id', collect([9, 10, 11])->map(fn (int $period): int => $timeSlots['A101 會議室'][$period]->id))
            ->delete();

        $multiGroupId = (string) Str::uuid();
        $multiReservations = collect([9, 10, 11])->map(function (int $period) use ($multiDate, $multiGroupId, $rooms, $professor, $timeSlots): Reservation {
            $reservation = Reservation::updateOrCreate(
                [
                    'user_id' => $professor->id,
                    'room_id' => $rooms['A101 會議室']->id,
                    'reservation_date' => $multiDate,
                    'time_slot_id' => $timeSlots['A101 會議室'][$period]->id,
                ],
                [
                    'reservation_group_id' => $multiGroupId,
                    'reservation_status' => 'success',
                    'payment_status' => 'unpaid',
                ],
            );

            return $reservation;
        });

        $firstMultiRes = $multiReservations->first();

        if ($firstMultiRes !== null) {
            $amount = $multiReservations->sum(fn (Reservation $reservation): int => (int) $reservation->timeSlot?->price);

            if ($amount > 0) {
                Payment::updateOrCreate(
                    ['reservation_id' => $firstMultiRes->id],
                    ['amount' => $amount],
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

        $cancelledReservation = Reservation::updateOrCreate(
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

        $pendingReservation = Reservation::updateOrCreate(
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
