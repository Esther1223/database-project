<?php

namespace App\Services;

use App\Models\Payment;
use App\Models\ReserveTimeslot;
use App\Models\Reservation;
use App\Models\Room;
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

            $rooms = Room::query()
                ->whereIn('id', collect($selectedSlots)->pluck('room_id')->unique()->values())
                ->get()
                ->keyBy('id');
            $reservations = [];
            $groupId = (string) Str::uuid();

            foreach ($selectedSlots as $slot) {
                /** @var Room $room */
                $room = $rooms->get($slot['room_id']) ?? Room::findOrFail($slot['room_id']);
                $slotData = [
                    'room_id' => $room->id,
                    'date' => $slot['date'],
                    'time_slot_id' => $slot['time_slot_id'],
                ];

                $timeSlot = $this->timeSlotForRoom($slotData, $room);

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
                $this->syncReserveTimeslot($reservation, $timeSlot->id);

                $reservations[] = $reservation->fresh(['room', 'timeSlot', 'payment']);
            }

            $this->createGroupPaymentIfNeeded($reservations);

            return $reservations;
        });
    }

    /**
     * @return array<int, array{room_id: int, date: string, time_slot_id: int}>
     */
    private function selectedSlotsFromData(array $data): array
    {
        if (! empty($data['selected_slots'] ?? [])) {
            return collect($data['selected_slots'])
                ->map(fn (array $slot): array => [
                    'room_id' => (int) $slot['room_id'],
                    'date' => (string) $slot['date'],
                    'time_slot_id' => (int) $slot['time_slot_id'],
                ])
                ->unique(fn (array $slot): string => $slot['room_id'].'|'.$slot['date'].'|'.$slot['time_slot_id'])
                ->sortBy(fn (array $slot): string => $slot['date'].' '.$slot['room_id'].' '.$slot['time_slot_id'])
                ->values()
                ->all();
        }

        $roomId = (int) ($data['room_id'] ?? 0);
        $dates = $data['dates'] ?? [$data['date'] ?? null];
        $timeSlotIds = array_values(array_unique(array_map('intval', $data['time_slot_ids'] ?? [])));

        return collect($dates)
            ->filter()
            ->flatMap(fn (string $date): array => collect($timeSlotIds)
                ->map(fn (int $timeSlotId): array => [
                    'room_id' => $roomId,
                    'date' => $date,
                    'time_slot_id' => $timeSlotId,
                ])
                ->all())
            ->unique(fn (array $slot): string => $slot['room_id'].'|'.$slot['date'].'|'.$slot['time_slot_id'])
            ->sortBy(fn (array $slot): string => $slot['date'].' '.$slot['room_id'].' '.$slot['time_slot_id'])
            ->values()
            ->all();
    }

    private function createReservation(array $data, int $userId): Reservation
    {
        $room = Room::findOrFail($data['room_id']);
        $timeSlot = $this->timeSlotForRoom($data, $room);
        $date = (string) $data['date'];

        if ($this->hasPendingSlotForUser($userId, $room->id, $date, (int) $timeSlot->id)) {
            abort(422, '已送出相同時段的審核申請，取消後才可重新送出。');
        }

        if ($this->hasConflict($room->id, $date, (int) $timeSlot->id)) {
            abort(422, '該時段已有預約');
        }

        $reservationStatus = $room->need_approval ? 'pending' : 'success';

        $reservation = Reservation::create([
            'user_id' => $userId,
            'reservation_group_id' => (string) Str::uuid(),
            'room_id' => $room->id,
            'reservation_date' => $date,
            'time_slot_id' => (int) $timeSlot->id,
            'reservation_status' => $reservationStatus,
        ]);
        $this->syncReserveTimeslot($reservation, $timeSlot->id);

        if ($reservationStatus === 'success') {
            $this->createPaymentIfNeeded($reservation);
        }

        return $reservation->fresh(['room', 'timeSlot', 'payment']);
    }

    private function timeSlotForRoom(array $data, Room $room): TimeSlot
    {
        return TimeSlot::query()
            ->whereKey($data['time_slot_id'])
            ->where('room_id', $room->id)
            ->firstOrFail();
    }

    public function cancel(Reservation $reservation): Reservation
    {
        return $this->cancelReservations($reservation, true);
    }

    public function cancelSingle(Reservation $reservation): Reservation
    {
        return $this->cancelReservations($reservation, false);
    }

    private function cancelReservations(Reservation $reservation, bool $cancelGroup): Reservation
    {
        DB::transaction(function () use ($reservation, $cancelGroup): void {
            $reservation->refresh();

            if (! in_array($reservation->reservation_status, ['pending', 'success'], true)) {
                abort(422, '此預約目前不能取消');
            }

            $reservations = $cancelGroup && $reservation->reservation_group_id
                ? Reservation::query()
                    ->where('reservation_group_id', $reservation->reservation_group_id)
                    ->whereIn('reservation_status', ['pending', 'success'])
                    ->lockForUpdate()
                    ->get()
                : collect([$reservation]);

            if ($reservations->contains(fn (Reservation $item): bool => $this->hasStarted($item))) {
                abort(422, '預約已開始，無法取消。');
            }

            foreach ($reservations as $item) {
                $item->update([
                    'reservation_status' => 'cancelled',
                    'payment_status' => 'cancelled',
                ]);

            }

            $reservationIds = $reservations->pluck('id')->filter()->values();

            if (! $cancelGroup && $reservationIds->isNotEmpty()) {
                Payment::whereIn('reservation_id', $reservationIds)->delete();
            }

            if (! $cancelGroup && $reservation->reservation_group_id) {
                $this->createGroupPaymentIfNeeded(
                    Reservation::query()
                        ->where('reservation_group_id', $reservation->reservation_group_id)
                        ->where('reservation_status', 'success')
                        ->get()
                        ->all(),
                );
            }
        });

        return $reservation->fresh(['room', 'timeSlot']);
    }

    private function hasStarted(Reservation $reservation): bool
    {
        $reservation->loadMissing('timeSlot');

        return $reservation->start_time !== null && $reservation->start_time->lessThanOrEqualTo(now());
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

    private function syncReserveTimeslot(Reservation $reservation, int $timeSlotId): void
    {
        ReserveTimeslot::updateOrCreate(
            ['reservation_id' => $reservation->id, 'time_slot_id' => $timeSlotId],
            [],
        );
    }

    private function createPaymentIfNeeded(Reservation $reservation): void
    {
        $amount = $this->feeService->calculateAmount($reservation);

        if ($amount <= 0) {
            return;
        }

        $payment = Payment::firstOrNew(['reservation_id' => $reservation->id]);
        $payment->amount = $amount;
        $payment->save();
    }

    /**
     * @param  array<int, Reservation>  $reservations
     */
    private function createGroupPaymentIfNeeded(array $reservations): void
    {
        $reservations = collect($reservations)
            ->filter(fn (Reservation $reservation): bool => $reservation->reservation_status === 'success')
            ->values();

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
        $payment->save();
    }
}
