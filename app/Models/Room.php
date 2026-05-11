<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'room_name',
        'room_type',
        'capacity',
        'building',
        'information',
        'hourly_rate',
        'need_approval',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'hourly_rate' => 'integer',
            'need_approval' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<RoomSection, $this>
     */
    public function roomSections(): HasMany
    {
        return $this->hasMany(RoomSection::class);
    }
}