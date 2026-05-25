<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class TimeSlot extends Model
{
    use HasFactory;

    protected $fillable = [
        'time_slot_id',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'status' => 'string',
        ];
    }

    /**
     * @return HasMany<RoomSection, $this>
     */
    public function roomSections(): HasMany
    {
        return $this->hasMany(RoomSection::class, 'time_slot_id', 'time_slot_id');
    }
}