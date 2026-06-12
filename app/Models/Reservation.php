<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Casts\Attribute;
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
        'reservation_status',
        'payment_status',
    ];

    protected $appends = [
        'start_time',
        'end_time',
    ];

    protected function casts(): array
    {
        return [
            'reservation_date' => 'date',
            'time_slot_id' => 'integer',
        ];
    }

    protected function startTime(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reservationDateTime($this->timeSlot?->period),
        );
    }

    protected function endTime(): Attribute
    {
        return Attribute::make(
            get: fn () => $this->reservationDateTime($this->timeSlot?->period === null ? null : $this->timeSlot->period + 1),
        );
    }

    private function reservationDateTime(?int $hour): ?\Carbon\Carbon
    {
        if ($hour === null || $this->reservation_date === null) {
            return null;
        }

        return $this->reservation_date->copy()->setTime($hour, 0);
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

    /**
     * @return BelongsTo<TimeSlot, $this>
     */
    public function timeSlot(): BelongsTo
    {
        return $this->belongsTo(TimeSlot::class);
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
