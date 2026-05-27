<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Reservation extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reservation_group_id',
        'room_id',
        'reservation_date',
        'time_slot_id',
        'start_time',
        'end_time',
        'reservation_status',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'start_time' => 'datetime',
            'end_time' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    public function approval(): HasOne
    {
        return $this->hasOne(Approval::class);
    }

    public function payment(): HasOne
    {
        return $this->hasOne(Payment::class);
    }
}
