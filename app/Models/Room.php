<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Room extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'type',
        'rate',
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

    protected function name(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['room_name'] ?? null,
            set: fn (string $value): array => ['room_name' => $value],
        );
    }

    protected function type(): Attribute
    {
        return Attribute::make(
            get: fn (): ?string => $this->attributes['room_type'] ?? null,
            set: fn (string $value): array => ['room_type' => $value],
        );
    }

    protected function rate(): Attribute
    {
        return Attribute::make(
            get: fn (): int => (int) ($this->attributes['hourly_rate'] ?? 0),
            set: fn (int|string $value): array => ['hourly_rate' => (int) $value],
        );
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