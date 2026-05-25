<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class RoomSectionService
{
    public function generateDailySections(int $daysAhead = 30): int
    {
        Log::info('時段可借狀態改為即時推導，無需批次產生可借資料');

        return 0;
    }

    public function ensureForRoomDate(Room $room, string $date): void
    {
        Log::info("{$room->id} @ {$date} 的可借時段由 time_slots 即時推導");
    }

    public function initializeForRoom(Room $room, int $daysAhead = 30): void
    {
        Log::info("教室 {$room->name} 的時段不再預先建立可借資料");
    }

    public function cleanUpOldSections(int $daysToKeep = 30): int
    {
        $cutoffDate = Carbon::today()->subDays($daysToKeep)->toDateString();

        $deletedCount = RoomSection::query()->where('date', '<', $cutoffDate)->delete();

        Log::info("已清理 {$cutoffDate} 之前的歷史時段，共刪除 {$deletedCount} 筆資料");

        return $deletedCount;
    }

    /**
     * @return array<int, string>
     */
    private function timeSlotIds(): array
    {
        return TimeSlot::query()
            ->orderByRaw('CAST(time_slot_id AS UNSIGNED)')
            ->pluck('time_slot_id')
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function sectionRow(int $roomId, string $date, string $timeSlotId): array
    {
        return [
            'room_id' => $roomId,
            'date' => $date,
            'time_slot_id' => $timeSlotId,
            'status' => 'available',
            'created_at' => now(),
            'updated_at' => now(),
        ];
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     */
    private function upsertRows(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        RoomSection::upsert(
            $rows,
            ['room_id', 'date', 'time_slot_id'],
            ['status', 'updated_at'],
        );
    }
}