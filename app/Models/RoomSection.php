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
            'time_slot_id' => 'integer',
        ];
    }

    protected function isBookable(): Attribute
    {
        return Attribute::make(
            get: function (): bool {
                return $this->status === 'available';
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
        return $this->belongsTo(TimeSlot::class);
    }
}
