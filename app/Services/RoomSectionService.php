<?php

namespace App\Services;

use App\Models\Room;
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
        Log::info("{$room->id} @ {$date} 的可借時段由該教室的 time_slots 即時推導");
    }

    public function initializeForRoom(Room $room, int $daysAhead = 30): void
    {
        Log::info("教室 {$room->name} 的時段不再預先建立可借資料");
    }

    public function cleanUpOldSections(int $daysToKeep = 30): int
    {
        Log::info('room_sections 已移除，無需清理歷史時段資料');

        return 0;
    }
}
