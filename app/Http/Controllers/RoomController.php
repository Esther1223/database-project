<?php

namespace App\Http\Controllers;

use App\Http\Requests\Room\StoreRoomRequest;
use App\Http\Requests\Room\UpdateRoomRequest;
use App\Models\Department;
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

        $filters = [
            'search' => trim((string) $request->query('search', '')),
            'type' => trim((string) $request->query('type', '')),
            'building' => trim((string) $request->query('building', '')),
        ];

        $query = Room::query()->orderBy('room_name');

        $user = $request->user();
        if ($user !== null) {
            $query->bookableForUser($user);
        }

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

        $departments = Department::query()
            ->orderBy('name')
            ->get()
            ->map(fn (Department $department): array => [
                'id' => $department->id,
                'name' => $department->name,
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
                'departments' => $departments,
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

        $reservedSections = $room->roomSections()
            ->where('date', $selectedDate)
            ->get()
            ->keyBy('time_slot_id');

        $sections = TimeSlot::query()
            ->orderByRaw('CAST(time_slot_id AS UNSIGNED)')
            ->get()
            ->map(function (TimeSlot $timeSlot) use ($reservedSections, $room, $selectedDate): array {
                $reservedSection = $reservedSections->get($timeSlot->time_slot_id);
                $state = $this->sectionState($timeSlot->status, $reservedSection?->status);

                return [
                    'id' => $reservedSection?->id,
                    'room_id' => $room->id,
                    'date' => $selectedDate,
                    'time_slot_id' => $timeSlot->time_slot_id,
                    'status' => $reservedSection?->status ?? 'available',
                    'state' => $state,
                    'time_label' => $this->formatTimeSlotLabel($timeSlot->time_slot_id),
                    'time_slot' => [
                        'id' => $timeSlot->id,
                        'time_slot_id' => $timeSlot->time_slot_id,
                        'status' => $timeSlot->status,
                        'label' => $this->formatTimeSlotLabel($timeSlot->time_slot_id),
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

        // sync open access departments if provided
        if (! empty($validated['open_access_departments'])) {
            $room->openDepartments()->sync($validated['open_access_departments']);
        }

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

        // sync open access departments
        if (array_key_exists('open_access_departments', $validated)) {
            $room->openDepartments()->sync($validated['open_access_departments'] ?? []);
        }

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
            'department_id' => $room->department_id,
            'rate' => $room->rate,
            'need_approval' => (bool) $room->need_approval,
            'is_open_access' => (bool) $room->is_open_access,
            'open_access_departments' => $room->openDepartments()->get()->map(fn (Department $d): array => ['id' => $d->id, 'name' => $d->name])->values()->all(),
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
            'department_id' => $validated['department_id'] ?? null,
            'information' => $validated['information'] ?? null,
            'rate' => (int) $validated['hourly_rate'],
            'need_approval' => (bool) $validated['need_approval'],
            'is_open_access' => (bool) $validated['is_open_access'],
        ];
    }

    private function sectionState(?string $timeSlotStatus, ?string $sectionStatus): string
    {
        if ($timeSlotStatus === 'disable') {
            return 'disabled';
        }

        if ($sectionStatus === 'reserved') {
            return 'reserved';
        }

        return 'available';
    }

    private function formatTimeSlotLabel(string $timeSlotId): string
    {
        if (ctype_digit($timeSlotId)) {
            $startHour = (int) $timeSlotId;

            return sprintf('%02d:00 - %02d:00', $startHour % 24, ($startHour + 1) % 24);
        }

        if (preg_match('/^TS_(\d{2})00$/', $timeSlotId, $matches) === 1) {
            $startHour = 8 + (int) $matches[1];

            return sprintf('%02d:00 - %02d:00', $startHour % 24, ($startHour + 1) % 24);
        }

        return $timeSlotId;
    }
}
