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
            'status' => 'required|in:enable,disable',
        ]);

        $timeSlot->update(['status' => $validated['status']]);

        $statusText = $validated['status'] === 'enable' ? '啟用' : '停用';

        return response()->json([
            'message' => "全域時段 {$timeSlot->time_slot_id} 已成功 {$statusText}",
            'data' => [
                'id' => $timeSlot->id,
                'time_slot_id' => $timeSlot->time_slot_id,
                'status' => $timeSlot->fresh()->status,
                'label' => $this->formatTimeSlotLabel($timeSlot->time_slot_id),
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

        return TimeSlot::query()
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
                    'is_bookable' => $state === 'available',
                    'time_slot' => [
                        'id' => $timeSlot->id,
                        'time_slot_id' => $timeSlot->time_slot_id,
                        'status' => $timeSlot->status,
                        'label' => $this->formatTimeSlotLabel($timeSlot->time_slot_id),
                    ],
                ];
            });
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
            $endHour = $startHour + 1;

            return sprintf('%02d:00 - %02d:00', $startHour, $endHour);
        }

        if (preg_match('/^TS_(\d{2})00$/', $timeSlotId, $matches) !== 1) {
            return $timeSlotId;
        }

        $startHour = (int) $matches[1];
        $endHour = $startHour + 1;

        return sprintf('%02d:00 - %02d:00', $startHour, $endHour);
    }
}