<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RoomSectionController extends Controller
{

    public function getAvailable(Request $request, Room $room): JsonResponse
    {
        $query = $room->roomsections()
            ->with('timeSlot')
            ->where('status', 'available')
            ->whereHas('timeSlot', function ($q) {
                $q->where('status', '!=', 'disable');
            });

        if ($request->has('date')) {
            $query->where('date', $request->query('date'));
        }

        $sections = $query->orderBy('date')->orderBy('time_slot_id')->get();

        return response()->json(['data' => $sections]);
    }

    public function getDisabled(Request $request, Room $room): JsonResponse
    {
        $query = $room->roomsections()
            ->with('timeSlot')
            ->where(function ($q) {
                $q->whereIn('status', ['unavailable', 'reserved'])
                  ->orWhereHas('timeSlot', function ($subQ) {
                      $subQ->where('status', 'disable');
                  });
            });

        if ($request->has('date')) {
            $query->where('date', $request->query('date'));
        }

        $sections = $query->orderBy('date')->orderBy('time_slot_id')->get();

        return response()->json(['data' => $sections]);
    }

    public function updateAvailability(Request $request, RoomSection $roomSection): JsonResponse
    {

        $validated = $request->validate([
            'status' => 'required|in:available,unavailable,reserved'
        ]);

        $roomSection->update([
            'status' => $validated['status']
        ]);

        return response()->json([
            'message' => '教室時段狀態已更新',
            'data' => $roomSection->fresh()
        ]);
    }

    public function updateAdminDisable(Request $request, TimeSlot $timeSlot): JsonResponse
    { 

        $validated = $request->validate([
            'status' => 'required|in:enable,disable'
        ]);

        $timeSlot->update([
            'status' => $validated['status']
        ]);

        $statusText = $validated['status'] === 'enable' ? '啟用' : '停用';

        return response()->json([
            'message' => "全域時段 {$timeSlot->time_slot_id} 已成功 {$statusText}",
            'data' => $timeSlot->fresh()
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $request->validate([
            'room_id' => 'required|exists:rooms,id',
            'section_id' => 'required|exists:room_sections,id',
        ]);

        $room = Room::findOrFail($request->room_id);
        $section = RoomSection::findOrFail($request->section_id);

        // 防呆：確認該時段是否真的可以借
        if ($section->status !== 'available') {
            return response()->json(['message' => '該時段已被借走或關閉'], 422);
        }

        // 判斷這間教室需不需要審核
        $reservationStatus = $room->need_approval ? 'pending' : 'success';

        $timeMap = [
            'TS_0000' => ['08:00:00', '09:00:00'],
            'TS_0100' => ['09:00:00', '10:00:00'],
            'TS_0200' => ['10:00:00', '11:00:00'],
            'TS_0300' => ['11:00:00', '12:00:00'],
            'TS_0400' => ['12:00:00', '13:00:00'],
            'TS_0500' => ['13:00:00', '14:00:00'],
            'TS_0600' => ['14:00:00', '15:00:00'],
            'TS_0700' => ['15:00:00', '16:00:00'],
            'TS_0800' => ['16:00:00', '17:00:00'],
            'TS_0900' => ['17:00:00', '18:00:00'],
            'TS_1000' => ['18:00:00', '19:00:00'],
            'TS_1100' => ['19:00:00', '20:00:00'],
            'TS_1200' => ['20:00:00', '21:00:00'],
        ];
        
        $timeRange = $timeMap[$section->time_slot_id] ?? ['00:00:00', '00:00:00'];
        $pureDate = \Carbon\Carbon::parse($section->date)->format('Y-m-d');
        $startTime = $pureDate . ' ' . $timeRange[0];
        $endTime = $pureDate . ' ' . $timeRange[1];

        $reservation = Reservation::create([
            'user_id' => Auth::id() ?? 3, 
            'room_id' => $room->id,
            'start_time' => $startTime,
            'end_time' => $endTime,
            'reservation_status' => $reservationStatus,
        ]);
        if ($reservationStatus === 'success') {
            $section->update(['status' => 'reserved']);
        }

        return response()->json([
            'message' => $reservationStatus === 'pending' ? '申請已送出，等待行政人員審核' : '預約成功！',
        ]);
    }
}