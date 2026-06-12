<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Affiliation;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RoomController extends Controller
{
    /**
     * Display the room list or management page.
     */
    public function index(Request $request): Response
    {
        $this->authorize('viewAny', Room::class);
        $isAdminRoomPage = $request->routeIs('admin.rooms.index');
        $visibleRoomsQuery = Room::query();

        if (! $isAdminRoomPage) {
            $visibleRoomsQuery
                ->bookableForUser($request->user())
                ->whereHas('timeSlots');
        }

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => trim((string) $request->query('type', '')),
            'building' => trim((string) $request->query('building', '')),
            'capacity_min' => trim((string) $request->query('capacity_min', '')),
            'affiliation_id' => trim((string) $request->query('affiliation_id', '')),
            'open_access' => trim((string) $request->query('open_access', '')),
        ];

        $query = (clone $visibleRoomsQuery)->orderBy('room_name');

        $query->when($filters['search'] !== '', function ($builder) use ($filters): void {
            $builder->where(function ($searchBuilder) use ($filters): void {
                $searchBuilder
                    ->where('room_name', 'like', '%'.$filters['search'].'%')
                    ->orWhere('room_type', 'like', '%'.$filters['search'].'%')
                    ->orWhere('building', 'like', '%'.$filters['search'].'%')
                    ->orWhere('information', 'like', '%'.$filters['search'].'%');
            });
        });

        $query->when($filters['type'] !== '', fn ($builder) => $builder->where('room_type', $filters['type']));
        $query->when($filters['building'] !== '', fn ($builder) => $builder->where('building', $filters['building']));
        $query->when($filters['capacity_min'] !== '', fn ($builder) => $builder->where('capacity', '>=', (int) $filters['capacity_min']));
        $query->when($filters['affiliation_id'] !== '', fn ($builder) => $builder->where('affiliation_id', (int) $filters['affiliation_id']));
        $query->when($filters['open_access'] !== '', fn ($builder) => $builder->where('is_open_access', $filters['open_access'] === '1'));

        $roomsPaginator = $query->paginate(8)->withQueryString();

        $rooms = [
            'data' => $roomsPaginator->getCollection()->map(fn (Room $room): array => $this->roomPayload($room))->values()->all(),
            'meta' => [
                'current_page' => $roomsPaginator->currentPage(),
                'last_page' => $roomsPaginator->lastPage(),
                'per_page' => $roomsPaginator->perPage(),
                'total' => $roomsPaginator->total(),
                'from' => $roomsPaginator->firstItem(),
                'to' => $roomsPaginator->lastItem(),
                'prev_page_url' => $roomsPaginator->previousPageUrl(),
                'next_page_url' => $roomsPaginator->nextPageUrl(),
            ],
        ];

        $roomTypes = (clone $visibleRoomsQuery)
            ->select('room_type')
            ->distinct()
            ->orderBy('room_type')
            ->pluck('room_type')
            ->values()
            ->all();

        $buildings = (clone $visibleRoomsQuery)
            ->select('building')
            ->distinct()
            ->orderBy('building')
            ->pluck('building')
            ->values()
            ->all();

        $visibleAffiliationIds = (clone $visibleRoomsQuery)
            ->select('affiliation_id')
            ->whereNotNull('affiliation_id')
            ->distinct()
            ->pluck('affiliation_id');

        $affiliations = Affiliation::query()
            ->whereIn('id', $visibleAffiliationIds)
            ->orderBy('name')
            ->get()
            ->map(fn (Affiliation $affiliation): array => [
                'id' => $affiliation->id,
                'name' => $affiliation->name,
            ])
            ->values()
            ->all();

        return Inertia::render(
            $request->routeIs('admin.rooms.index') ? 'Admin/Rooms/RoomManagePage' : 'Rooms/RoomListPage',
            [
                'rooms' => $rooms,
                'filters' => $filters,
                'roomTypes' => $roomTypes,
                'buildings' => $buildings,
                'affiliations' => $affiliations,
            ],
        );
    }

    /**
     * Display the specified room.
     */
    public function show(Request $request, Room $room): Response
    {
        $this->authorize('view', $room);

        $selectedDate = $request->query('date', now()->toDateString());

        $reservedTimeSlotIds = Reservation::query()
            ->where('room_id', $room->id)
            ->whereDate('reservation_date', $selectedDate)
            ->where('reservation_status', 'success')
            ->pluck('time_slot_id')
            ->map(fn ($timeSlotId): int => (int) $timeSlotId)
            ->flip();
        $pendingTimeSlotIds = Reservation::query()
            ->where('user_id', $request->user()?->id)
            ->where('room_id', $room->id)
            ->whereDate('reservation_date', $selectedDate)
            ->where('reservation_status', 'pending')
            ->pluck('time_slot_id')
            ->map(fn ($timeSlotId): int => (int) $timeSlotId)
            ->flip();

        $sections = $room->timeSlots()
            ->orderBy('period')
            ->get()
            ->map(function (TimeSlot $timeSlot) use ($reservedTimeSlotIds, $pendingTimeSlotIds, $room, $selectedDate): array {
                $state = $this->sectionState(
                    $selectedDate,
                    $timeSlot,
                    $reservedTimeSlotIds->has($timeSlot->id),
                    $pendingTimeSlotIds->has($timeSlot->id),
                );

                return [
                    'id' => null,
                    'room_id' => $room->id,
                    'date' => $selectedDate,
                    'time_slot_id' => $timeSlot->id,
                    'status' => $state === 'reserved' ? 'reserved' : 'available',
                    'state' => $state,
                    'time_label' => $timeSlot->label(),
                    'time_slot' => [
                        'id' => $timeSlot->id,
                        'period' => $timeSlot->period,
                        'price' => $timeSlot->price,
                        'label' => $timeSlot->label(),
                    ],
                ];
            });

        return Inertia::render('Rooms/RoomDetailPage', [
            'room' => $this->roomPayload($room),
            'sections' => $sections,
            'currentDate' => $selectedDate,
        ]);
    }

    /**
     * Store a newly created room.
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $this->authorize('create', Room::class);

        $validated = $request->validated();
        $room = Room::create($this->roomStoragePayload($validated));
        $this->syncDefaultTimeSlots($room);

        $room->openAffiliations()->sync($validated['open_access_affiliations'] ?? []);

        return response()->json([
            'message' => '空間已建立',
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    /**
     * Update the specified room.
     */
    public function update(UpdateRoomRequest $request, Room $room): JsonResponse
    {
        $this->authorize('update', $room);

        $validated = $request->validated();
        $room->forceFill($this->roomStoragePayload($validated))->save();
        $this->syncDefaultTimeSlots($room);

        $room->openAffiliations()->sync($validated['open_access_affiliations'] ?? []);

        return response()->json([
            'message' => '空間已更新',
            'room' => $this->roomPayload($room->fresh()),
        ]);
    }

    /**
     * Remove the specified room.
     */
    public function destroy(Room $room): JsonResponse
    {
        $this->authorize('delete', $room);

        $room->delete();

        return response()->json([
            'message' => '空間已刪除',
        ]);
    }

    /**
     * Prepare room data for API responses and Inertia props.
     */
    private function roomPayload(Room $room): array
    {
        return [
            'id' => $room->id,
            'name' => $room->name,
            'type' => $room->type,
            'capacity' => $room->capacity,
            'building' => $room->building,
            'affiliation_id' => $room->affiliation_id,
            'price_label' => $this->priceLabel($room),
            'need_approval' => (bool) $room->need_approval,
            'is_open_access' => (bool) $room->is_open_access,
            'open_access_all' => (bool) $room->open_access_all,
            'open_access_affiliations' => $room->openAffiliations()->get()->map(fn (Affiliation $d): array => ['id' => $d->id, 'name' => $d->name])->values()->all(),
            'time_slots' => $room->timeSlots()
                ->orderBy('period')
                ->get()
                ->map(fn (TimeSlot $timeSlot): array => [
                    'id' => $timeSlot->id,
                    'period' => $timeSlot->period,
                    'price' => $timeSlot->price,
                    'label' => $timeSlot->label(),
                ])
                ->values()
                ->all(),
            'information' => $room->information,
            'created_at' => $room->created_at?->toDateTimeString(),
            'updated_at' => $room->updated_at?->toDateTimeString(),
        ];
    }

    private function priceLabel(Room $room): string
    {
        $prices = $room->timeSlots()
            ->pluck('price')
            ->map(fn ($price): int => (int) $price)
            ->unique()
            ->sort()
            ->values();

        if ($prices->isEmpty()) {
            return '尚未建立時段';
        }

        if ($prices->count() === 1) {
            return '每時段 NT$ '.$prices->first();
        }

        return 'NT$ '.$prices->first().' - '.$prices->last();
    }

    /**
     * Convert validated form data into the storage shape.
     */
    private function roomStoragePayload(array $validated): array
    {
        return [
            'name' => $validated['name'],
            'type' => $validated['type'],
            'capacity' => (int) $validated['capacity'],
            'building' => $validated['building'],
            'affiliation_id' => $validated['affiliation_id'],
            'information' => $validated['information'] ?? null,
            'need_approval' => (bool) $validated['need_approval'],
            'is_open_access' => (bool) $validated['is_open_access'],
            'open_access_all' => (bool) ($validated['is_open_access'] && ($validated['open_access_all'] ?? false)),
        ];
    }

    private function syncDefaultTimeSlots(Room $room): void
    {
        foreach (range(8, 21) as $period) {
            $room->timeSlots()->firstOrCreate(
                ['period' => $period],
                ['price' => 0],
            );
        }
    }

    private function sectionState(?string $date = null, ?TimeSlot $timeSlot = null, bool $hasSuccessReservation = false, bool $hasPendingReservation = false): string
    {
        if ($hasSuccessReservation) {
            return 'reserved';
        }

        if ($hasPendingReservation) {
            return 'pending';
        }

        if ($date !== null && $timeSlot !== null && $this->slotStartsInPast($date, $timeSlot)) {
            return 'expired';
        }

        return 'available';
    }

    private function slotStartsInPast(string $date, TimeSlot $timeSlot): bool
    {
        return \Carbon\Carbon::parse(sprintf('%s %02d:00:00', $date, $timeSlot->period))->lessThanOrEqualTo(now());
    }
}
