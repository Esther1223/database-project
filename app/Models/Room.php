<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'afflication_id',
        'information',
        'hourly_rate',
        'need_approval',
        'is_open_access',
        'open_access_all',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
            'hourly_rate' => 'integer',
            'need_approval' => 'boolean',
            'is_open_access' => 'boolean',
            'open_access_all' => 'boolean',
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

    /**
     * @return HasMany<TimeSlot, $this>
     */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    /**
     * Afflications that are allowed to access this room (cross-open).
     */
    public function openAfflications()
    {
        return $this->belongsToMany(Afflication::class, 'afflication_room');
    }

    /**
     * @return BelongsTo<Afflication, $this>
     */
    public function afflication(): BelongsTo
    {
        return $this->belongsTo(Afflication::class);
    }

    public function scopeBookableForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('afflication_id', $user->afflication_id)
                ->orWhere(function (Builder $openBuilder) use ($user): void {
                    $openBuilder
                        ->where('is_open_access', true)
                        ->where(function (Builder $accessBuilder) use ($user): void {
                            $accessBuilder
                                ->where('open_access_all', true)
                                ->orWhereHas('openAfflications', fn (Builder $query) => $query->where('afflications.id', $user->afflication_id));
                        });
                });
        });
    }

    public function isBookableBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->afflication_id === (int) $user->afflication_id) {
            return true;
        }

        if (! $this->is_open_access) {
            return false;
        }

        if ($this->open_access_all) {
            return true;
        }

        return $this->openAfflications()
            ->where('afflications.id', $user->afflication_id)
            ->exists();
    }
}
