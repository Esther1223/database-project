<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    private const ACTIVE_STATUSES = ['success'];

    private const TIME_MAP = [
        'TS_0000' => ['08:00:00', '09:00:00'],
        'TS_0100' => ['09:00:00', '10:00:00'],
        'TS_0200' => ['10:00:00', '11:00:00'],
        'TS_0300' => ['11:00:00', '12:00:00'],
        'TS_0400' => ['12:00:00', '13:00:00'],
        'TS_0500' => ['13:00:00', '14:00:00'],
        'TS_0600' => ['14:00:00', '15:00:00'],
        'TS_0700' => ['15:00:00', '16:00:00'],
        'TS_0800' => ['16:00:00', '17:00:00'],
        'TS_0900' => ['17:00:00', '18:00:00'],
        'TS_1000' => ['18:00:00', '19:00:00'],
        'TS_1100' => ['19:00:00', '20:00:00'],
        'TS_1200' => ['20:00:00', '21:00:00'],
        'TS_1300' => ['21:00:00', '22:00:00'],
        'TS_1400' => ['22:00:00', '23:00:00'],
        'TS_1500' => ['23:00:00', '00:00:00'],
        'TS_1600' => ['00:00:00', '01:00:00'],
    ];

    public function __construct(private readonly FeeService $feeService) {}

    public function create(array $data, int $userId): Reservation
    {
        return DB::transaction(fn (): Reservation => $this->createReservation($data, $userId));
    }

    /**
     * @return array<int, Reservation>
     */
    public function createMany(array $data, int $userId): array
    {
        return DB::transaction(function () use ($data, $userId): array {
            $selectedSlots = $this->selectedSlotsFromData($data);

            if (empty($selectedSlots)) {
                return [];
            }

            $room = Room::findOrFail($data['room_id']);
            $reservations = [];
            $groupId = (string) Str::uuid();

            foreach ($selectedSlots as $slot) {
                $slotData = [
                    'room_id' => $room->id,
                    'date' => $slot['date'],
                    'time_slot_id' => $slot['time_slot_id'],
                ];

                $section = $this->lockOrCreateSection($slotData, $room);

                if ($section->status !== 'available') {
                    abort(422, '所選時段已被借走或關閉');
                }

                if ($this->hasPendingSlotForUser($userId, $room->id, $slot['date'], $slot['time_slot_id'])) {
                    abort(422, '已送出相同時段的審核申請，取消後才可重新送出。');
                }

                [$startTime, $endTime] = $this->sectionDateTimeRange($section);

                if ($this->hasConflict($room->id, $startTime, $endTime)) {
                    abort(422, '該時段已有預約');
                }

                $reservationStatus = $room->need_approval ? 'pending' : 'success';

                $reservation = Reservation::create([
                    'user_id' => $userId,
                    'reservation_group_id' => $groupId,
                    'room_id' => $room->id,
                    'reservation_date' => $slot['date'],
                    'time_slot_id' => $slot['time_slot_id'],
                    'start_time' => $startTime,
                    'end_time' => $endTime,
                    'reservation_status' => $reservationStatus,
                ]);

                if ($reservationStatus === 'success') {
                    $section->update(['status' => 'reserved']);
                }

                $reservations[] = $reservation->fresh(['room', 'payment']);
            }

            if (($reservations[0]?->reservation_status ?? null) === 'success') {
                $this->createGroupPaymentIfNeeded($reservations);
            }

            return $reservations;
        });
    }

    /**
     * @return array<int, array{date: string, time_slot_id: string}>
     */
    private function selectedSlotsFromData(array $data): array
    {
        if (! empty($data['selected_slots'] ?? [])) {
            return collect($data['selected_slots'])
                ->map(fn (array $slot): array => [
                    'date' => (string) $slot['date'],
                    'time_slot_id' => (string) $slot['time_slot_id'],
                ])
                ->unique(fn (array $slot): string => $slot['date'].'|'.$slot['time_slot_id'])
                ->sortBy(fn (array $slot): string => $slot['date'].' '.$slot['time_slot_id'])
                ->values()
                ->all();
        }

        $dates = $data['dates'] ?? [$data['date'] ?? null];
        $timeSlotIds = array_values(array_unique($data['time_slot_ids'] ?? []));

        return collect($dates)
            ->filter()
            ->flatMap(fn (string $date): array => collect($timeSlotIds)
                ->map(fn (string $timeSlotId): array => [
                    'date' => $date,
                    'time_slot_id' => $timeSlotId,
                ])
                ->all())
            ->unique(fn (array $slot): string => $slot['date'].'|'.$slot['time_slot_id'])
            ->sortBy(fn (array $slot): string => $slot['date'].' '.$slot['time_slot_id'])
            ->values()
            ->all();
    }

    private function createReservation(array $data, int $userId): Reservation
    {
        $room = Room::findOrFail($data['room_id']);
        $section = $this->lockOrCreateSection($data, $room);

        if ($section->status !== 'available') {
            abort(422, '該時段已被借走或關閉');
        }

        [$startTime, $endTime] = $this->sectionDateTimeRange($section);
        $date = Carbon::parse($section->date)->format('Y-m-d');

        if ($this->hasPendingSlotForUser($userId, $room->id, $date, (string) $section->time_slot_id)) {
            abort(422, '已送出相同時段的審核申請，取消後才可重新送出。');
        }

        if ($this->hasConflict($room->id, $startTime, $endTime)) {
            abort(422, '該時段已有預約');
        }

        $reservationStatus = $room->need_approval ? 'pending' : 'success';

        $reservation = Reservation::create([
            'user_id' => $userId,
            'reservation_group_id' => (string) Str::uuid(),
            'room_id' => $room->id,
            'reservation_date' => $date,
            'time_slot_id' => (string) $section->time_slot_id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reservation_status' => $reservationStatus,
        ]);

        if ($reservationStatus === 'success') {
            $section->update(['status' => 'reserved']);
            $this->createPaymentIfNeeded($reservation);
        }

        return $reservation->fresh(['room', 'payment']);
    }

    private function lockOrCreateSection(array $data, Room $room): RoomSection
    {
        if (! empty($data['section_id'])) {
            return RoomSection::whereKey($data['section_id'])
                ->where('room_id', $room->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $section = RoomSection::where('room_id', $room->id)
            ->whereDate('date', $data['date'])
            ->where('time_slot_id', $data['time_slot_id'])
            ->lockForUpdate()
            ->first();

        if ($section !== null) {
            return $section;
        }

        return RoomSection::create([
            'room_id' => $room->id,
            'date' => $data['date'],
            'time_slot_id' => $data['time_slot_id'],
            'status' => 'available',
        ]);
    }

    public function cancel(Reservation $reservation): Reservation
    {
        DB::transaction(function () use ($reservation): void {
            $reservation->refresh();

            if (! in_array($reservation->reservation_status, ['pending', 'success'], true)) {
                abort(422, '此預約目前不能取消');
            }

            if ($reservation->reservation_group_id) {
                $reservations = Reservation::query()
                    ->where('reservation_group_id', $reservation->reservation_group_id)
                    ->whereIn('reservation_status', ['pending', 'success'])
                    ->lockForUpdate()
                    ->get();
            } else {
                $reservations = collect([$reservation]);
            }

            foreach ($reservations as $item) {
                $wasSuccess = $item->reservation_status === 'success';

                $item->update([
                    'reservation_status' => 'cancelled',
                ]);

                if ($wasSuccess) {
                    $this->releaseRoomSection($item);
                }
            }

            $reservationIds = $reservations->pluck('id')->filter()->values();

            if ($reservationIds->isNotEmpty()) {
                Payment::whereIn('reservation_id', $reservationIds)->delete();
            }
        });

        return $reservation->fresh('room');
    }

    public function hasPendingSlotForUser(int $userId, int $roomId, string $date, string $timeSlotId): bool
    {
        return Reservation::query()
            ->where('user_id', $userId)
            ->where('room_id', $roomId)
            ->whereDate('reservation_date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->where('reservation_status', 'pending')
            ->lockForUpdate()
            ->exists();
    }

    public function hasConflict(int $roomId, string $startTime, string $endTime): bool
    {
        return Reservation::where('room_id', $roomId)
            ->whereIn('reservation_status', self::ACTIVE_STATUSES)
            ->where('start_time', '<', $endTime)
            ->where('end_time', '>', $startTime)
            ->lockForUpdate()
            ->exists();
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function sectionDateTimeRange(RoomSection $section): array
    {
        $timeRange = $this->timeRangeForSlot($section->time_slot_id);
        $date = Carbon::parse($section->date)->format('Y-m-d');
        $startTime = Carbon::parse("{$date} {$timeRange[0]}");
        $endTime = Carbon::parse("{$date} {$timeRange[1]}");

        if ($endTime->lessThanOrEqualTo($startTime)) {
            $endTime->addDay();
        }

        return [
            $startTime->toDateTimeString(),
            $endTime->toDateTimeString(),
        ];
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function timeRangeForSlot(string $timeSlotId): array
    {
        if (ctype_digit($timeSlotId)) {
            $startHour = (int) $timeSlotId;
            $endHour = $startHour + 1;

            return [
                sprintf('%02d:00:00', $startHour % 24),
                sprintf('%02d:00:00', $endHour % 24),
            ];
        }

        return self::TIME_MAP[$timeSlotId] ?? ['00:00:00', '00:00:00'];
    }

    private function releaseRoomSection(Reservation $reservation): void
    {
        $date = $reservation->reservation_date?->format('Y-m-d') ?? Carbon::parse($reservation->start_time)->format('Y-m-d');
        $timeSlotIds = $reservation->time_slot_id
            ? [(string) $reservation->time_slot_id]
            : $this->timeSlotIdsForStartTime($reservation->start_time);

        if (empty($timeSlotIds)) {
            return;
        }

        RoomSection::where('room_id', $reservation->room_id)
            ->where('date', $date)
            ->whereIn('time_slot_id', $timeSlotIds)
            ->where('status', 'reserved')
            ->update(['status' => 'available']);
    }

    /**
     * @return array<int, string>
     */
    private function timeSlotIdsForStartTime(Carbon|string $startTime): array
    {
        $hour = Carbon::parse($startTime)->hour;
        $slotNumber = $hour - 8;

        if ($slotNumber < 0 || $slotNumber > 12) {
            return [(string) $hour];
        }

        return [
            (string) $hour,
            sprintf('TS_%04d', $slotNumber * 100),
        ];
    }

    private function createPaymentIfNeeded(Reservation $reservation): void
    {
        $amount = $this->feeService->calculateAmount($reservation);

        if ($amount <= 0) {
            return;
        }

        $payment = Payment::firstOrNew(['reservation_id' => $reservation->id]);
        $payment->amount = $amount;

        if (! $payment->exists) {
            $payment->payment_status = 'unpaid';
        }

        $payment->save();
    }

    /**
     * @param  array<int, Reservation>  $reservations
     */
    private function createGroupPaymentIfNeeded(array $reservations): void
    {
        $reservations = collect($reservations)->filter();

        if ($reservations->isEmpty()) {
            return;
        }

        /** @var Reservation $representative */
        $representative = $reservations->sortBy('start_time')->first();
        $amount = (int) $reservations->sum(fn (Reservation $reservation): int => $this->feeService->calculateAmount($reservation));

        Payment::whereIn('reservation_id', $reservations->pluck('id')->filter()->values())
            ->where('reservation_id', '!=', $representative->id)
            ->delete();

        if ($amount <= 0) {
            Payment::where('reservation_id', $representative->id)->delete();

            return;
        }

        $payment = Payment::firstOrNew(['reservation_id' => $representative->id]);
        $payment->amount = $amount;

        if (! $payment->exists) {
            $payment->payment_status = 'unpaid';
        }

        $payment->save();
    }
}
