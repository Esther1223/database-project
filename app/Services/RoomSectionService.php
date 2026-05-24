<?php

namespace App\Services;

use App\Models\Room;
use App\Models\RoomSection;
use App\Models\TimeSlot;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class RoomSectionService
{

    public function generateDailySections(int $daysAhead = 30): void
    {
        $targetDate = Carbon::today()->addDays($daysAhead)->toDateString();
        $timeSlots = TimeSlot::pluck('time_slot_id')->toArray();
        
        if (empty($timeSlots)) {
            Log::warning('無法產生時段：TimeSlot 資料表為空');
            return;
        }

        Room::chunk(50, function ($rooms) use ($targetDate, $timeSlots) {
            $insertData = [];

            foreach ($rooms as $room) {
                foreach ($timeSlots as $slotId) {
                    $insertData[] = [
                        'room_id' => $room->id,
                        'date' => $targetDate,
                        'time_slot_id' => $slotId,
                        'status' => 'available',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ];
                }
            }

            RoomSection::insertOrIgnore($insertData);
        });

        Log::info("成功產生 {$targetDate} 的所有教室時段");
    }


    public function initializeForRoom(Room $room, int $daysAhead = 30): void
    {
        $timeSlots = TimeSlot::pluck('time_slot_id')->toArray();
        $today = Carbon::today();
        $insertData = [];

        for ($i = 0; $i <= $daysAhead; $i++) {
            $currentDate = $today->copy()->addDays($i)->toDateString();

            foreach ($timeSlots as $slotId) {
                $insertData[] = [
                    'room_id' => $room->id,
                    'date' => $currentDate,
                    'time_slot_id' => $slotId,
                    'status' => 'available',
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }

        RoomSection::insertOrIgnore($insertData);
        
        Log::info("教室 {$room->name} 的未來 {$daysAhead} 天時段已初始化完成");
    }

    public function cleanUpOldSections(int $daysToKeep = 30): void
    {
        $cutoffDate = Carbon::today()->subDays($daysToKeep)->toDateString();

        $deletedCount = RoomSection::where('date', '<', $cutoffDate)->delete();

        Log::info("已清理 {$cutoffDate} 之前的歷史時段，共刪除 {$deletedCount} 筆資料");
    }
}