<?php

namespace App\Http\Controllers;

use App\Models\Room;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TimeSlotController extends Controller
{
    public function index(Room $room): JsonResponse
    {
        $this->authorize('update', $room);

        return response()->json([
            'data' => $room->timeSlots()
                ->orderBy('period')
                ->get()
                ->map(fn (TimeSlot $timeSlot): array => $this->payload($timeSlot))
                ->values(),
        ]);
    }

    public function store(Request $request, Room $room): JsonResponse
    {
        $this->authorize('update', $room);

        $validated = $request->validate([
            'period' => [
                'required',
                'integer',
                'min:0',
                'max:23',
                Rule::unique('Time_slot', 'period')->where('room_id', $room->id),
            ],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $timeSlot = $room->timeSlots()->create($validated);

        return response()->json([
            'message' => '時段已建立',
            'data' => $this->payload($timeSlot),
        ], 201);
    }

    public function update(Request $request, Room $room, TimeSlot $timeSlot): JsonResponse
    {
        $this->authorize('update', $room);
        $this->ensureBelongsToRoom($room, $timeSlot);

        $validated = $request->validate([
            'period' => [
                'required',
                'integer',
                'min:0',
                'max:23',
                Rule::unique('Time_slot', 'period')->where('room_id', $room->id)->ignore($timeSlot->id),
            ],
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $timeSlot->update($validated);

        return response()->json([
            'message' => '時段已更新',
            'data' => $this->payload($timeSlot->fresh()),
        ]);
    }

    public function updateStatus(Request $request, TimeSlot $timeSlot): JsonResponse
    {
        $room = $timeSlot->room;
        $this->authorize('update', $room);

        $validated = $request->validate([
            'price' => ['required', 'integer', 'min:0'],
        ]);

        $timeSlot->update(['price' => $validated['price']]);

        return response()->json([
            'message' => '時段價格已更新',
            'data' => $this->payload($timeSlot->fresh()),
        ]);
    }

    public function destroy(Room $room, TimeSlot $timeSlot): JsonResponse
    {
        $this->authorize('update', $room);
        $this->ensureBelongsToRoom($room, $timeSlot);

        if ($timeSlot->reservations()->exists()) {
            return response()->json([
                'message' => '此時段已有預約或狀態紀錄，不可刪除。',
            ], 422);
        }

        $timeSlot->delete();

        return response()->json([
            'message' => '時段已刪除',
        ]);
    }

    private function ensureBelongsToRoom(Room $room, TimeSlot $timeSlot): void
    {
        abort_unless((int) $timeSlot->room_id === (int) $room->id, 404);
    }

    private function payload(TimeSlot $timeSlot): array
    {
        return [
            'id' => $timeSlot->id,
            'room_id' => $timeSlot->room_id,
            'period' => $timeSlot->period,
            'price' => $timeSlot->price,
            'label' => $timeSlot->label(),
        ];
    }
}
