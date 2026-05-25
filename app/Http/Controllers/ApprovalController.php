<?php

namespace App\Http\Controllers;

use App\Http\Requests\Approval\DecisionApprovalRequest;
use App\Models\Approval;
use App\Models\Reservation;
use App\Services\ApprovalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ApprovalController extends Controller
{
    public function __construct(private readonly ApprovalService $approvalService) {}

    /**
     * Render admin approvals page.
     */
    public function index(): Response
    {
        $this->authorize('approve', new Reservation);

        return Inertia::render('Admin/Approvals/ApprovalListPage');
    }

    /**
     * Return pending reservations for review.
     */
    public function pending(): JsonResponse
    {
        $this->authorize('approve', new Reservation);

        $reservations = Reservation::with(['room', 'approval', 'user'])
            ->where('reservation_status', 'pending')
            ->latest('start_time')
            ->get();

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
            ->get();

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
}
