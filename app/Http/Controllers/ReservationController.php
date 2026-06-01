<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Services\ReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService) {}

    public function index(): Response
    {
        $this->authorize('viewAny', Reservation::class);

        return Inertia::render('Reservations/MyReservationsPage');
    }

    public function create(): Response
    {
        $this->authorize('create', Reservation::class);

        $rooms = Room::query()
            ->bookableForUser(Auth::user())
            ->orderBy('room_name')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'type' => $room->type,
                'capacity' => $room->capacity,
                'building' => $room->building,
                'rate' => $room->rate,
                'need_approval' => (bool) $room->need_approval,
                'information' => $room->information,
            ])
            ->values()
            ->all();

        // Default the reservation initial date to today's date
        $initialDate = now()->toDateString();

        return Inertia::render('Reservations/CreateReservationPage', [
            'rooms' => $rooms,
            'initialDate' => $initialDate,
        ]);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reservations = null;

        if (! empty($validated['selected_slots'] ?? []) || ! empty($validated['time_slot_ids'] ?? [])) {
            $reservations = $this->reservationService->createMany($validated, Auth::id());
        } else {
            $reservations = [$this->reservationService->create($validated, Auth::id())];
        }

        $status = $reservations[0]->reservation_status ?? 'success';
        $message = $status === 'pending'
            ? '申請已送出，等待行政人員審核'
            : '預約成功！';

        if (count($reservations) > 1) {
            $message = $message.' 已包含 '.count($reservations).' 個時段。';
        }

        return response()->json([
            'message' => $message,
            'data' => $reservations,
        ], 201);
    }

    public function cancel(Reservation $reservation): JsonResponse
    {
        $this->authorize('delete', $reservation);

        $reservation = $this->reservationService->cancel($reservation);

        return response()->json([
            'message' => '預約已取消',
            'data' => $reservation,
        ]);
    }

    public function myReservations(): JsonResponse
    {
        $this->authorize('viewAny', Reservation::class);

        $reservations = Reservation::with(['room', 'approval', 'payment', 'timeSlot'])
            ->where('user_id', Auth::id())
            ->latest('reservation_date')
            ->latest('time_slot_id')
            ->get()
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservation_group_id ?: (string) $reservation->id)
            ->map(fn ($group): array => $this->reservationGroupPayload($group))
            ->values();

        return response()->json([
            'data' => $reservations,
        ]);
    }

    public function history(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
            'status' => ['nullable', 'string', 'in:pending,success,cancelled,rejected'],
        ]);

        $reservations = Reservation::with(['room.afflication', 'user.afflication', 'payment', 'timeSlot'])
            ->when($validated['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('reservation_date', '>=', $date))
            ->when($validated['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('reservation_date', '<=', $date))
            ->when($validated['room_id'] ?? null, fn ($query, int|string $roomId) => $query->where('room_id', $roomId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('reservation_status', $status))
            ->latest('reservation_date')
            ->latest('time_slot_id')
            ->limit(200)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'start_time' => $reservation->start_time?->toDateTimeString(),
                'end_time' => $reservation->end_time?->toDateTimeString(),
                'reservation_status' => $reservation->reservation_status,
                'created_at' => $reservation->created_at?->toDateTimeString(),
                'room' => $reservation->room
                    ? [
                        'id' => $reservation->room->id,
                        'name' => $reservation->room->name,
                        'type' => $reservation->room->type,
                        'building' => $reservation->room->building,
                        'afflication_name' => $reservation->room->afflication?->name,
                    ]
                    : null,
                'user' => $reservation->user
                    ? [
                        'id' => $reservation->user->id,
                        'name' => $reservation->user->name,
                        'email' => $reservation->user->email,
                        'afflication_name' => $reservation->user->afflication?->name,
                    ]
                    : null,
                'payment' => $reservation->payment
                    ? [
                        'id' => $reservation->payment->id,
                        'amount' => $reservation->payment->amount,
                        'payment_status' => $reservation->payment->payment_status,
                    ]
                    : null,
            ]);

        return response()->json([
            'data' => $reservations,
            'filters' => $validated,
        ]);
    }

    public function show(Reservation $reservation): JsonResponse
    {
        $this->authorize('view', $reservation);

        return response()->json([
            'data' => $reservation->load(['room', 'approval', 'payment', 'timeSlot']),
        ]);
    }

    private function reservationGroupPayload($group): array
    {
        $sorted = $group->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->values();
        /** @var Reservation $first */
        $first = $sorted->first();
        /** @var Reservation $last */
        $last = $sorted->last();
        $payments = $sorted->pluck('payment')->filter();

        return [
            'id' => $first->id,
            'reservation_group_id' => $first->reservation_group_id,
            'start_time' => $first->start_time?->toDateTimeString(),
            'end_time' => $last->end_time?->toDateTimeString(),
            'reservation_status' => $this->groupStatus($sorted),
            'created_at' => $first->created_at?->toDateTimeString(),
            'room' => $first->room ? [
                'id' => $first->room->id,
                'name' => $first->room->name,
                'type' => $first->room->type,
                'building' => $first->room->building,
            ] : null,
            'approval' => $first->approval ? [
                'id' => $first->approval->id,
                'status' => $first->approval->status,
            ] : null,
            'payment' => $payments->isEmpty() ? null : [
                'amount' => $payments->sum('amount'),
                'payment_status' => $payments->contains(fn ($payment): bool => $payment->payment_status === 'unpaid') ? 'unpaid' : 'paid',
            ],
            'slots' => $sorted
                ->map(fn (Reservation $reservation): array => [
                    'id' => $reservation->id,
                    'date' => $reservation->reservation_date?->format('Y-m-d') ?? $reservation->start_time?->format('Y-m-d'),
                    'time_slot_id' => $reservation->time_slot_id,
                    'start_time' => $reservation->start_time?->toDateTimeString(),
                    'end_time' => $reservation->end_time?->toDateTimeString(),
                    'reservation_status' => $reservation->reservation_status,
                ])
                ->all(),
        ];
    }

    private function groupStatus($reservations): string
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
