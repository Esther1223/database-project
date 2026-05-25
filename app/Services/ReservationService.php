<?php

namespace App\Services;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

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

    public function create(array $data, int $userId): Reservation
    {
        return DB::transaction(function () use ($data, $userId): Reservation {
            $room = Room::findOrFail($data['room_id']);
            $section = $this->lockOrCreateSection($data, $room);

            if ($section->status !== 'available') {
                abort(422, '該時段已被借走或關閉');
            }

            [$startTime, $endTime] = $this->sectionDateTimeRange($section);

            if ($this->hasConflict($room->id, $startTime, $endTime)) {
                abort(422, '該時段已有預約');
            }

            $reservationStatus = $room->need_approval ? 'pending' : 'success';

            $reservation = Reservation::create([
                'user_id' => $userId,
                'room_id' => $room->id,
                'start_time' => $startTime,
                'end_time' => $endTime,
                'reservation_status' => $reservationStatus,
            ]);

            if ($reservationStatus === 'success') {
                $section->update(['status' => 'reserved']);
            }

            return $reservation->load('room');
        });
    }

    private function lockOrCreateSection(array $data, Room $room): RoomSection
    {
        if (!empty($data['section_id'])) {
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

            if (!in_array($reservation->reservation_status, ['pending', 'success'], true)) {
                abort(422, '此預約目前不能取消');
            }

            $wasSuccess = $reservation->reservation_status === 'success';

            $reservation->update([
                'reservation_status' => 'cancelled',
            ]);

            if ($wasSuccess) {
                $this->releaseRoomSection($reservation);
            }
        });

        return $reservation->fresh('room');
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
        $date = Carbon::parse($reservation->start_time)->format('Y-m-d');
        $timeSlotIds = $this->timeSlotIdsForStartTime($reservation->start_time);

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
}
