<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Room;
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

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => trim((string) $request->query('type', '')),
            'building' => trim((string) $request->query('building', '')),
        ];

        $query = Room::query()->orderBy('room_name');

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

        $roomTypes = Room::query()
            ->select('room_type')
            ->distinct()
            ->orderBy('room_type')
            ->pluck('room_type')
            ->values()
            ->all();

        $buildings = Room::query()
            ->select('building')
            ->distinct()
            ->orderBy('building')
            ->pluck('building')
            ->values()
            ->all();

        return Inertia::render(
            $request->routeIs('admin.rooms.index') ? 'Admin/Rooms/RoomManagePage' : 'Rooms/RoomListPage',
            [
                'rooms' => $rooms,
                'filters' => $filters,
                'roomTypes' => $roomTypes,
                'buildings' => $buildings,
            ],
        );
    }

    /**
     * Display the specified room.
     */
    public function show(Room $room): Response
    {
        $this->authorize('view', $room);

        return Inertia::render('Rooms/RoomDetailPage', [
            'room' => $this->roomPayload($room),
        ]);
    }

    /**
     * Store a newly created room.
     */
    public function store(StoreRoomRequest $request): JsonResponse
    {
        $this->authorize('create', Room::class);

        $room = Room::create($this->roomStoragePayload($request->validated()));

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

        $room->forceFill($this->roomStoragePayload($request->validated()))->save();

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
            'rate' => $room->rate,
            'need_approval' => (bool) $room->need_approval,
            'information' => $room->information,
            'created_at' => $room->created_at?->toDateTimeString(),
            'updated_at' => $room->updated_at?->toDateTimeString(),
        ];
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
            'information' => $validated['information'] ?? null,
            'rate' => (int) $validated['hourly_rate'],
            'need_approval' => (bool) $validated['need_approval'],
        ];
    }
}