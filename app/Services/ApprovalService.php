<?php

namespace App\Services;

use App\Models\Approval;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\RoomSection;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ApprovalService
{
    public function __construct(private readonly FeeService $feeService) {}

    /**
     * Approve a pending reservation: create approval record, set reservation_status to 'success',
     * and mark related room sections as 'reserved'.
     */
    public function approveReservation(int $reservationId, int $approverId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $approverId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);
            $reservations = $this->reservationsInReviewGroup($reservation);

            if ($reservations->isEmpty() || $reservations->contains(fn (Reservation $item): bool => $item->reservation_status !== 'pending')) {
                abort(422, '此預約目前不能被審核');
            }

            foreach ($reservations as $item) {
                if ($this->slotAlreadyReserved($item)) {
                    abort(422, '此時段已被其他預約核准。');
                }
            }

            Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approverId,
                'decision' => Approval::DECISION_APPROVED,
                'decision_time' => Carbon::now(),
            ]);

            foreach ($reservations as $item) {
                $item->update(['reservation_status' => 'success']);

                $this->reserveRoomSections($item);
                $this->rejectConflictingPendingReservations($item);
            }

            $this->createGroupPaymentIfNeeded($this->successReservationsInPaymentGroup($reservation));

            return $reservation->fresh(['room', 'payment']);
        });
    }

    /**
     * Reject a pending reservation: create approval record and set reservation_status to 'rejected'.
     */
    public function rejectReservation(int $reservationId, int $approverId): Reservation
    {
        return DB::transaction(function () use ($reservationId, $approverId) {
            $reservation = Reservation::lockForUpdate()->findOrFail($reservationId);
            $reservations = $this->reservationsInReviewGroup($reservation);

            if ($reservations->isEmpty() || $reservations->contains(fn (Reservation $item): bool => $item->reservation_status !== 'pending')) {
                abort(422, '此預約目前不能被審核');
            }

            Approval::create([
                'reservation_id' => $reservation->id,
                'approver_id' => $approverId,
                'decision' => Approval::DECISION_REJECTED,
                'decision_time' => Carbon::now(),
            ]);

            foreach ($reservations as $item) {
                $item->update(['reservation_status' => 'rejected']);
            }

            return $reservation->fresh('room');
        });
    }

    private function reservationsInReviewGroup(Reservation $reservation)
    {
        if (! $reservation->reservation_group_id) {
            return collect([$reservation]);
        }

        return Reservation::query()
            ->where('reservation_group_id', $reservation->reservation_group_id)
            ->where('reservation_status', 'pending')
            ->lockForUpdate()
            ->get();
    }

    private function successReservationsInPaymentGroup(Reservation $reservation)
    {
        if (! $reservation->reservation_group_id) {
            return Reservation::query()
                ->whereKey($reservation->id)
                ->where('reservation_status', 'success')
                ->lockForUpdate()
                ->get();
        }

        return Reservation::query()
            ->where('reservation_group_id', $reservation->reservation_group_id)
            ->where('reservation_status', 'success')
            ->lockForUpdate()
            ->get();
    }

    private function reserveRoomSections(Reservation $reservation): void
    {
        $date = $reservation->reservation_date?->format('Y-m-d');

        if ($date === null || $reservation->time_slot_id === null) {
            return;
        }

        RoomSection::updateOrCreate(
            [
                'room_id' => $reservation->room_id,
                'date' => $date,
                'time_slot_id' => $reservation->time_slot_id,
            ],
            [
                'status' => 'reserved',
            ],
        );
    }

    private function slotAlreadyReserved(Reservation $reservation): bool
    {
        return Reservation::query()
            ->whereKeyNot($reservation->id)
            ->where('room_id', $reservation->room_id)
            ->where('reservation_status', 'success')
            ->whereDate('reservation_date', $reservation->reservation_date)
            ->where('time_slot_id', $reservation->time_slot_id)
            ->lockForUpdate()
            ->exists();
    }

    private function rejectConflictingPendingReservations(Reservation $approvedReservation): void
    {
        Reservation::query()
            ->whereKeyNot($approvedReservation->id)
            ->where('room_id', $approvedReservation->room_id)
            ->whereDate('reservation_date', $approvedReservation->reservation_date)
            ->where('time_slot_id', $approvedReservation->time_slot_id)
            ->where('reservation_status', 'pending')
            ->update([
                'reservation_status' => 'rejected',
                'payment_status' => 'cancelled',
            ]);
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

    private function createGroupPaymentIfNeeded($reservations): void
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
