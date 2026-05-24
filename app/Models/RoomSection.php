<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Casts\Attribute;

class RoomSection extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_id',
        'date',
        'time_slot_id',
        'status',
    ];

    protected $appends = [
        'is_bookable'
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
        ];
    }

    protected function isBookable(): Attribute
    {
        return Attribute::make(
            get: function () {
                $isSectionAvailable = $this->status === 'available';
                $isTimeSlotEnabled = $this->timeSlot?->status !== 'disable';
                return $isSectionAvailable && $isTimeSlotEnabled;
            }
        );
    }
    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return BelongsTo<TimeSlot, $this>
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class, 'time_slot_id', 'time_slot_id');
    }
}