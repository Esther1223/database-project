<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Reservation;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    /**
     * Approve a pending reservation: create approval record, set reservation_status to 'success',
     * and mark related room sections as 'reserved'.
     */
    public function approveReservation(int $reservationId, int $approverId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $approverId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->reservation_status !== 'pending') {
                abort(422, '此預約目前不能被審核');
            }

            Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approverId,
                'decision' => Approval::DECISION_APPROVED,
                'decision_time' => Carbon::now(),
            ]);

            $reservation->update(['reservation_status' => 'success']);

            // mark room sections as reserved
            $this->reserveRoomSections($reservation);

            return $reservation->fresh('room');
        });
    }

    /**
     * Reject a pending reservation: create approval record and set reservation_status to 'rejected'.
     */
    public function rejectReservation(int $reservationId, int $approverId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $approverId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);

            if ($reservation->reservation_status !== 'pending') {
                abort(422, '此預約目前不能被審核');
            }

            Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approverId,
                'decision' => Approval::DECISION_REJECTED,
                'decision_time' => Carbon::now(),
            ]);

            $reservation->update(['reservation_status' => 'rejected']);

            return $reservation->fresh('room');
        });
    }

    private function reserveRoomSections(Reservation $reservation): void
    {
        $date = Carbon::parse($reservation->start_time)->format('Y-m-d');
        $hour = Carbon::parse($reservation->start_time)->hour;
        $timeSlotIds = $this->timeSlotIdsForStartTime($reservation->start_time);

        if (empty($timeSlotIds)) {
            return;
        }

        $validTimeSlotIds = TimeSlot::query()
            ->whereIn('time_slot_id', $timeSlotIds)
            ->pluck('time_slot_id')
            ->all();

        if (empty($validTimeSlotIds)) {
            return;
        }

        foreach ($validTimeSlotIds as $timeSlotId) {
            RoomSection::updateOrCreate(
                [
                    'room_id' => $reservation->room_id,
                    'date' => $date,
                    'time_slot_id' => $timeSlotId,
                ],
                [
                    'status' => 'reserved',
                ],
            );
        }
    }

    /**
     * Borrowed logic from ReservationService to compute time slot ids.
     *
     * @return array<int, string>
     */
    private function timeSlotIdsForStartTime(string $startTime): array
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
