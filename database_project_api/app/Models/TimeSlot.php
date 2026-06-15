<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    use HasFactory;

    protected $table = 'Time_slot';

    protected $fillable = [
        'room_id',
        'period',
        'price',
    ];

    protected function casts(): array
    {
        return [
            'period' => 'integer',
            'price' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Room, $this>
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    public function label(): string
    {
        return sprintf('%02d:00 - %02d:00', $this->period, $this->period + 1);
    }
}
