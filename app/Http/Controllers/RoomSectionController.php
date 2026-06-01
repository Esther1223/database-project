<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoomSectionController extends Controller
{
    public function getAvailable(Request $request, Room $room): JsonResponse
    {
        $selectedDate = $this->selectedDate($request);

        $sections = $this->allSectionsForDate($room, $selectedDate)
            ->values()
            ->all();

        return response()->json(['data' => $sections]);
    }

    public function getDisabled(Request $request, Room $room): JsonResponse
    {
        $selectedDate = $this->selectedDate($request);

        $sections = $this->allSectionsForDate($room, $selectedDate)
            ->filter(fn (array $section): bool => $section['state'] !== 'available')
            ->values()
            ->all();

        return response()->json(['data' => $sections]);
    }

    public function updateAvailability(Request $request, RoomSection $roomSection): JsonResponse
    {
        $validated = $request->validate([
            'status' => 'required|in:available,unavailable,reserved',
        ]);

        $roomSection->update(['status' => $validated['status']]);

        return response()->json([
            'message' => '教室時段狀態已更新',
            'data' => $this->sectionPayload($roomSection->fresh(['timeSlot'])),
        ]);
    }

    public function updateAdminDisable(Request $request, TimeSlot $timeSlot): JsonResponse
    {
        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $timeSlot->update(['price' => $validated['price']]);

        return response()->json([
            'message' => '時段價格已更新',
            'data' => [
                'id' => $timeSlot->id,
                'period' => $timeSlot->period,
                'price' => $timeSlot->fresh()->price,
                'label' => $timeSlot->label(),
            ],
        ]);
    }

    private function selectedDate(Request $request): string
    {
        $request->validate([
            'date' => 'nullable|date_format:Y-m-d',
        ]);

        return $request->input('date', Carbon::today()->toDateString());
    }

    /**
     * @return Collection<int, array<string, mixed>>
     */
    private function allSectionsForDate(Room $room, string $selectedDate): Collection
    {
        $reservedSections = $room->roomSections()
            ->with('timeSlot')
            ->whereDate('date', $selectedDate)
            ->get()
            ->keyBy('time_slot_id');

        return $room->timeSlots()
            ->orderBy('period')
            ->get()
            ->map(function (TimeSlot $timeSlot) use ($reservedSections, $room, $selectedDate): array {
                $reservedSection = $reservedSections->get($timeSlot->id);
                $state = $this->sectionState($reservedSection?->status, $selectedDate, $timeSlot);

                return [
                    'id' => $reservedSection?->id,
                    'room_id' => $room->id,
                    'date' => $selectedDate,
                    'time_slot_id' => $timeSlot->id,
                    'status' => $reservedSection?->status ?? 'available',
                    'state' => $state,
                    'is_bookable' => $state === 'available',
                    'time_slot' => [
                        'id' => $timeSlot->id,
                        'period' => $timeSlot->period,
                        'price' => $timeSlot->price,
                        'label' => $timeSlot->label(),
                    ],
                ];
            });
    }

    private function sectionState(?string $sectionStatus, ?string $date = null, ?TimeSlot $timeSlot = null): string
    {
        if ($sectionStatus === 'reserved') {
            return 'reserved';
        }

        if ($sectionStatus === 'unavailable') {
            return 'disabled';
        }

        if ($date !== null && $timeSlot !== null && $this->slotStartsInPast($date, $timeSlot)) {
            return 'expired';
        }

        return 'available';
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionPayload(RoomSection $section): array
    {
        $timeSlot = $section->timeSlot;
        $date = Carbon::parse($section->date)->format('Y-m-d');
        $state = $this->sectionState($section->status, $date, $timeSlot);

        return [
            'id' => $section->id,
            'room_id' => $section->room_id,
            'date' => $date,
            'time_slot_id' => $section->time_slot_id,
            'status' => $section->status,
            'state' => $state,
            'is_bookable' => $state === 'available',
            'time_slot' => $timeSlot === null ? null : [
                'id' => $timeSlot->id,
                'period' => $timeSlot->period,
                'price' => $timeSlot->price,
                'label' => $timeSlot->label(),
            ],
        ];
    }

    private function slotStartsInPast(string $date, TimeSlot $timeSlot): bool
    {
        return Carbon::parse(sprintf('%s %02d:00:00', $date, $timeSlot->period))->lessThanOrEqualTo(now());
    }
}
