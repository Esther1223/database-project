<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Render the payment management page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Payments/PaymentListPage');
    }

    /**
     * Return payment list for admin reconciliation.
     */
    public function list(): JsonResponse
    {
        $payments = Payment::with(['reservation.room', 'reservation.user', 'reservation.timeSlot'])
            ->latest('created_at')
            ->get()
            ->groupBy(fn (Payment $payment): string => $payment->reservation?->reservation_group_id ?: (string) $payment->reservation_id)
            ->map(fn (Collection $group): array => $this->paymentGroupPayload($group))
            ->values();

        return response()->json(['data' => $payments]);
    }

    /**
     * Update payment status.
     */
    public function updateStatus(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:paid,unpaid'],
        ]);

        $reservation = $payment->reservation;
        $reservations = $reservation
            ? Reservation::query()
                ->when(
                    $reservation->reservation_group_id,
                    fn ($query) => $query->where('reservation_group_id', $reservation->reservation_group_id),
                    fn ($query) => $query->whereKey($reservation->id),
                )
                ->get()
            : collect();
        $currentStatus = $this->paymentStatus($reservations);

        if ($currentStatus === 'paid' && $validated['payment_status'] === 'unpaid') {
            return response()->json([
                'message' => '已付款的資料不能再標記為未付款。',
            ], 422);
        }

        if ($currentStatus === 'cancelled') {
            return response()->json([
                'message' => '已取消的付款資料不能變更狀態。',
            ], 422);
        }

        if ($reservation?->reservation_group_id) {
            Reservation::query()
                ->where('reservation_group_id', $reservation->reservation_group_id)
                ->update(['payment_status' => $validated['payment_status']]);
        } elseif ($reservation) {
            $reservation->update(['payment_status' => $validated['payment_status']]);
        }

        return response()->json([
            'message' => '付款狀態已更新',
        ]);
    }

    private function paymentGroupPayload(Collection $group): array
    {
        $sortedPayments = $group->sortBy(fn (Payment $payment): string => $payment->reservation?->start_time?->toDateTimeString() ?? '');
        /** @var Payment $firstPayment */
        $firstPayment = $sortedPayments->first();
        $reservation = $firstPayment->reservation;
        $reservations = $reservation
            ? Reservation::with(['room', 'user', 'timeSlot'])
                ->when(
                    $reservation->reservation_group_id,
                    fn ($query) => $query->where('reservation_group_id', $reservation->reservation_group_id),
                    fn ($query) => $query->whereKey($reservation->id),
                )
                ->orderBy('reservation_date')
                ->orderBy('time_slot_id')
                ->get()
            : collect();

        if ($reservations->isEmpty() && $reservation !== null) {
            $reservations = collect([$reservation]);
        }

        $firstReservation = $reservations->first();
        $lastReservation = $reservations->last();

        return [
            'id' => $firstPayment->id,
            'payment_ids' => $group->pluck('id')->values()->all(),
            'amount' => $group->sum('amount'),
            'payment_status' => $this->paymentStatus($reservations),
            'created_at' => $group->sortBy('created_at')->first()?->created_at?->toDateTimeString(),
            'updated_at' => $group->sortByDesc('updated_at')->first()?->updated_at?->toDateTimeString(),
            'slot_count' => $reservations->count(),
            'reservation' => $firstReservation
                ? [
                    'id' => $firstReservation->id,
                    'reservation_group_id' => $firstReservation->reservation_group_id,
                    'start_time' => $firstReservation->start_time?->toDateTimeString(),
                    'end_time' => $lastReservation?->end_time?->toDateTimeString(),
                    'reservation_status' => $this->groupStatus($reservations),
                    'room' => $this->roomPayload($firstReservation),
                    'user' => $this->userPayload($firstReservation),
                ]
                : null,
            'slots' => $reservations
                ->map(fn (Reservation $reservation): array => [
                    'id' => $reservation->id,
                    'date' => $reservation->reservation_date?->format('Y-m-d') ?? $reservation->start_time?->format('Y-m-d'),
                    'time_slot_id' => $reservation->time_slot_id,
                    'start_time' => $reservation->start_time?->toDateTimeString(),
                    'end_time' => $reservation->end_time?->toDateTimeString(),
                    'reservation_status' => $reservation->reservation_status,
                    'room' => $this->roomPayload($reservation),
                ])
                ->values()
                ->all(),
        ];
    }

    private function roomPayload(Reservation $reservation): ?array
    {
        if (! $reservation->room) {
            return null;
        }

        return [
            'id' => $reservation->room->id,
            'name' => $reservation->room->name,
            'type' => $reservation->room->type,
            'building' => $reservation->room->building,
        ];
    }

    private function userPayload(Reservation $reservation): ?array
    {
        if (! $reservation->user) {
            return null;
        }

        return [
            'id' => $reservation->user->id,
            'name' => $reservation->user->name,
            'email' => $reservation->user->email,
        ];
    }

    private function groupStatus(Collection $reservations): string
    {
        $statuses = $reservations->pluck('reservation_status');

        foreach (['pending', 'success', 'cancelled', 'rejected'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return (string) ($statuses->first() ?? '-');
    }

    private function paymentStatus(Collection $reservations): string
    {
        if ($reservations->contains(fn (Reservation $reservation): bool => $reservation->payment_status === 'unpaid')) {
            return 'unpaid';
        }

        if ($reservations->contains(fn (Reservation $reservation): bool => $reservation->payment_status === 'cancelled')) {
            return 'cancelled';
        }

        return 'paid';
    }
}
