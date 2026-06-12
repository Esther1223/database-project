<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\TimeSlot;
use App\Models\Reservation;
use Carbon\Carbon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

class RoomSectionController extends Controller
{
    public function getAvailable(Request $request, Room $room): JsonResponse
    {
        $selectedDate = $this->selectedDate($request);

        $sections = $this->allSectionsForDate($room, $selectedDate, $request)
            ->values()
            ->all();

        return response()->json(['data' => $sections]);
    }

    public function getDisabled(Request $request, Room $room): JsonResponse
    {
        $selectedDate = $this->selectedDate($request);

        $sections = $this->allSectionsForDate($room, $selectedDate, $request)
            ->filter(fn (array $section): bool => $section['state'] !== 'available')
            ->values()
            ->all();

        return response()->json(['data' => $sections]);
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
    private function allSectionsForDate(Room $room, string $selectedDate, Request $request): Collection
    {
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

        return $room->timeSlots()
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
        return Carbon::parse(sprintf('%s %02d:00:00', $date, $timeSlot->period))->lessThanOrEqualTo(now());
    }
}
