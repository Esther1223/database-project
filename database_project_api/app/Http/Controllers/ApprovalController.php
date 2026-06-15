<?php

namespace App\Http\Controllers;

use App\Http\Requests\Approval\DecisionApprovalRequest;
use App\Models\Approval;
use App\Models\Reservation;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    /**
     * Render admin approvals page.
     */
    public function index(): JsonResponse
    {
        $this->authorize('approve', new Reservation);

        return response()->json([
            'message' => '審核 API 可用',
        ]);
    }

    /**
     * Return pending reservations for review.
     */
    public function pending(): JsonResponse
    {
        $this->authorize('approve', new Reservation);

        $reservations = Reservation::with(['room', 'approval', 'user', 'timeSlot'])
            ->where('reservation_status', 'pending')
            ->latest('reservation_date')
            ->latest('time_slot_id')
            ->get()
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservation_group_id ?: (string) $reservation->id)
            ->map(fn (Collection $group): array => $this->reservationGroupPayload($group))
            ->values();

        return response()->json(['data' => $reservations]);
    }

    /**
     * Return completed approval decisions.
     */
    public function history(): JsonResponse
    {
        $this->authorize('approve', new Reservation);

        $approvals = Approval::with(['approver', 'reservation.room', 'reservation.user'])
            ->latest('decision_time')
            ->get()
            ->groupBy(fn (Approval $approval): string => $approval->reservation?->reservation_group_id ?: (string) $approval->reservation_id)
            ->map(fn (Collection $group): array => $this->approvalGroupPayload($group))
            ->values();

        return response()->json(['data' => $approvals]);
    }

    public function approve(DecisionApprovalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reservationForAuthorization = Reservation::findOrFail($validated['reservation_id']);

        $this->authorize('approve', $reservationForAuthorization);

        $reservation = $this->approvalService->approveReservation($reservationForAuthorization->id, Auth::id());

        return response()->json([
            'message' => '審核已核准',
            'data' => $reservation,
        ]);
    }

    public function reject(DecisionApprovalRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reservationForAuthorization = Reservation::findOrFail($validated['reservation_id']);

        $this->authorize('approve', $reservationForAuthorization);

        $reservation = $this->approvalService->rejectReservation($reservationForAuthorization->id, Auth::id());

        return response()->json([
            'message' => '審核已拒絕',
            'data' => $reservation,
        ]);
    }

    private function reservationGroupPayload(Collection $group): array
    {
        $sorted = $group->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->values();
        /** @var Reservation $first */
        $first = $sorted->first();
        /** @var Reservation $last */
        $last = $sorted->last();

        return [
            'id' => $first->id,
            'reservation_group_id' => $first->reservation_group_id,
            'slot_count' => $sorted->count(),
            'start_time' => $first->start_time?->toDateTimeString(),
            'end_time' => $last->end_time?->toDateTimeString(),
            'created_at' => $first->created_at?->toDateTimeString(),
            'reservation_status' => $this->groupStatus($sorted),
            'room' => $first->room,
            'user' => $first->user,
            'slots' => $this->slotsPayload($sorted),
        ];
    }

    private function approvalGroupPayload(Collection $group): array
    {
        /** @var Approval $firstApproval */
        $firstApproval = $group->sortByDesc('decision_time')->first();
        $reservation = $firstApproval->reservation;
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

        $sortedReservations = $reservations->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->values();
        $firstReservation = $sortedReservations->first();
        $lastReservation = $sortedReservations->last();

        return [
            'id' => $firstApproval->id,
            'decision' => $firstApproval->decision,
            'decision_time' => $firstApproval->decision_time?->toDateTimeString(),
            'approver' => $firstApproval->approver,
            'slot_count' => $sortedReservations->count(),
            'reservation' => $firstReservation ? [
                'id' => $firstReservation->id,
                'reservation_group_id' => $firstReservation->reservation_group_id,
                'reservation_status' => $this->groupStatus($sortedReservations),
                'start_time' => $firstReservation->start_time?->toDateTimeString(),
                'end_time' => $lastReservation?->end_time?->toDateTimeString(),
                'room' => $firstReservation->room,
                'user' => $firstReservation->user,
            ] : null,
            'slots' => $this->slotsPayload($sortedReservations),
        ];
    }

    private function slotsPayload(Collection $reservations): array
    {
        return $reservations
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'date' => $reservation->reservation_date?->format('Y-m-d') ?? $reservation->start_time?->format('Y-m-d'),
                'time_slot_id' => $reservation->time_slot_id,
                'start_time' => $reservation->start_time?->toDateTimeString(),
                'end_time' => $reservation->end_time?->toDateTimeString(),
                'reservation_status' => $reservation->reservation_status,
                'room' => $reservation->room ? [
                    'id' => $reservation->room->id,
                    'name' => $reservation->room->name,
                    'type' => $reservation->room->type,
                    'building' => $reservation->room->building,
                ] : null,
            ])
            ->all();
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
}
