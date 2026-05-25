<?php

namespace App\Http\Controllers;

use App\Http\Requests\Reservation\StoreReservationRequest;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use App\Services\ReservationService;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class ReservationController extends Controller
{
    public function __construct(private readonly ReservationService $reservationService)
    {
    }

    public function index(): Response
    {
        $this->authorize('viewAny', Reservation::class);

        return Inertia::render('Reservations/MyReservationsPage');
    }

    public function create(): Response
    {
        $this->authorize('create', Reservation::class);

        $rooms = Room::query()
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

        $initialDate = RoomSection::query()
            ->orderBy('date')
            ->value('date') ?? now()->toDateString();
        $initialDate = Carbon::parse($initialDate)->toDateString();

        return Inertia::render('Reservations/CreateReservationPage', [
            'rooms' => $rooms,
            'initialDate' => $initialDate,
        ]);
    }

    public function store(StoreReservationRequest $request): JsonResponse
    {
        $validated = $request->validated();
        $reservations = null;

        if (!empty($validated['time_slot_ids'] ?? [])) {
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
            ->get();

        return response()->json([
            'data' => $reservations,
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
