<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use App\Services\ReservationService;
use Carbon\Carbon;
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

        if (! empty($validated['time_slot_ids'] ?? [])) {
            $reservations = $this->reservationService->createMany($validated, Auth::id());
        } else {
            $reservations = [$this->reservationService->create($validated, Auth::id())];
        }

        $status = $reservations[0]->reservation_status ?? 'success';
        $message = $status === 'pending'
            ? '申請已送出，等待行政人員審核'
            : '預約成功！';

        if (count($reservations) > 1) {
            $message = $message.' 已建立 '.count($reservations).' 筆預約。';
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

        $reservations = Reservation::with(['room', 'approval', 'payment'])
            ->where('user_id', Auth::id())
            ->latest('start_time')
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
                    ]
                    : null,
                'approval' => $reservation->approval ? [
                    'id' => $reservation->approval->id,
                    'status' => $reservation->approval->status,
                ] : null,
                'payment' => $reservation->payment ? [
                    'id' => $reservation->payment->id,
                    'amount' => $reservation->payment->amount,
                    'payment_status' => $reservation->payment->payment_status,
                ] : null,
            ]);

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

        $reservations = Reservation::with(['room.department', 'user.department', 'payment'])
            ->when($validated['start_date'] ?? null, fn ($query, string $date) => $query->whereDate('start_time', '>=', $date))
            ->when($validated['end_date'] ?? null, fn ($query, string $date) => $query->whereDate('start_time', '<=', $date))
            ->when($validated['room_id'] ?? null, fn ($query, int|string $roomId) => $query->where('room_id', $roomId))
            ->when($validated['status'] ?? null, fn ($query, string $status) => $query->where('reservation_status', $status))
            ->latest('start_time')
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
                        'department_name' => $reservation->room->department?->name,
                    ]
                    : null,
                'user' => $reservation->user
                    ? [
                        'id' => $reservation->user->id,
                        'name' => $reservation->user->name,
                        'email' => $reservation->user->email,
                        'department_name' => $reservation->user->department?->name,
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
            'data' => $reservation->load(['room', 'approval', 'payment']),
        ]);
    }
}
