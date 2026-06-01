<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReservationService
{
    private const ACTIVE_STATUSES = ['success'];

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

                if ($this->hasPendingSlotForUser($userId, $room->id, $slot['date'], (int) $slot['time_slot_id'])) {
                    abort(422, '已送出相同時段的審核申請，取消後才可重新送出。');
                }

                if ($this->hasConflict($room->id, $slot['date'], (int) $slot['time_slot_id'])) {
                    abort(422, '該時段已有預約');
                }

                $reservationStatus = $room->need_approval ? 'pending' : 'success';

                $reservation = Reservation::create([
                    'user_id' => $userId,
                    'reservation_group_id' => $groupId,
                    'room_id' => $room->id,
                    'reservation_date' => $slot['date'],
                    'time_slot_id' => (int) $slot['time_slot_id'],
                    'reservation_status' => $reservationStatus,
                ]);

                if ($reservationStatus === 'success') {
                    $section->update(['status' => 'reserved']);
                }

                $reservations[] = $reservation->fresh(['room', 'timeSlot', 'payment']);
            }

            if (($reservations[0]?->reservation_status ?? null) === 'success') {
                $this->createGroupPaymentIfNeeded($reservations);
            }

            return $reservations;
        });
    }

    /**
     * @return array<int, array{date: string, time_slot_id: int}>
     */
    private function selectedSlotsFromData(array $data): array
    {
        if (! empty($data['selected_slots'] ?? [])) {
            return collect($data['selected_slots'])
                ->map(fn (array $slot): array => [
                    'date' => (string) $slot['date'],
                    'time_slot_id' => (int) $slot['time_slot_id'],
                ])
                ->unique(fn (array $slot): string => $slot['date'].'|'.$slot['time_slot_id'])
                ->sortBy(fn (array $slot): string => $slot['date'].' '.$slot['time_slot_id'])
                ->values()
                ->all();
        }

        $dates = $data['dates'] ?? [$data['date'] ?? null];
        $timeSlotIds = array_values(array_unique(array_map('intval', $data['time_slot_ids'] ?? [])));

        return collect($dates)
            ->filter()
            ->flatMap(fn (string $date): array => collect($timeSlotIds)
                ->map(fn (int $timeSlotId): array => [
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
        $date = $section->date->format('Y-m-d');

        if ($section->status !== 'available') {
            abort(422, '該時段已被借走或關閉');
        }

        if ($this->hasPendingSlotForUser($userId, $room->id, $date, (int) $section->time_slot_id)) {
            abort(422, '已送出相同時段的審核申請，取消後才可重新送出。');
        }

        if ($this->hasConflict($room->id, $date, (int) $section->time_slot_id)) {
            abort(422, '該時段已有預約');
        }

        $reservationStatus = $room->need_approval ? 'pending' : 'success';

        $reservation = Reservation::create([
            'user_id' => $userId,
            'reservation_group_id' => (string) Str::uuid(),
            'room_id' => $room->id,
            'reservation_date' => $date,
            'time_slot_id' => (int) $section->time_slot_id,
            'reservation_status' => $reservationStatus,
        ]);

        if ($reservationStatus === 'success') {
            $section->update(['status' => 'reserved']);
            $this->createPaymentIfNeeded($reservation);
        }

        return $reservation->fresh(['room', 'timeSlot', 'payment']);
    }

    private function lockOrCreateSection(array $data, Room $room): RoomSection
    {
        if (! empty($data['section_id'])) {
            return RoomSection::whereKey($data['section_id'])
                ->where('room_id', $room->id)
                ->lockForUpdate()
                ->firstOrFail();
        }

        $timeSlot = TimeSlot::query()
            ->whereKey($data['time_slot_id'])
            ->where('room_id', $room->id)
            ->firstOrFail();

        $section = RoomSection::where('room_id', $room->id)
            ->whereDate('date', $data['date'])
            ->where('time_slot_id', $timeSlot->id)
            ->lockForUpdate()
            ->first();

        if ($section !== null) {
            return $section;
        }

        return RoomSection::create([
            'room_id' => $room->id,
            'date' => $data['date'],
            'time_slot_id' => $timeSlot->id,
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

            $reservations = $reservation->reservation_group_id
                ? Reservation::query()
                    ->where('reservation_group_id', $reservation->reservation_group_id)
                    ->whereIn('reservation_status', ['pending', 'success'])
                    ->lockForUpdate()
                    ->get()
                : collect([$reservation]);

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

        return $reservation->fresh(['room', 'timeSlot']);
    }

    public function hasPendingSlotForUser(int $userId, int $roomId, string $date, int $timeSlotId): bool
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

    public function hasConflict(int $roomId, string $date, int $timeSlotId): bool
    {
        return Reservation::where('room_id', $roomId)
            ->whereDate('reservation_date', $date)
            ->where('time_slot_id', $timeSlotId)
            ->whereIn('reservation_status', self::ACTIVE_STATUSES)
            ->lockForUpdate()
            ->exists();
    }

    private function releaseRoomSection(Reservation $reservation): void
    {
        $date = $reservation->reservation_date?->format('Y-m-d');

        if ($date === null || $reservation->time_slot_id === null) {
            return;
        }

        RoomSection::where('room_id', $reservation->room_id)
            ->whereDate('date', $date)
            ->where('time_slot_id', $reservation->time_slot_id)
            ->where('status', 'reserved')
            ->update(['status' => 'available']);
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
        $representative = $reservations->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->first();
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
