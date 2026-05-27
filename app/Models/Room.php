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
        'department_id',
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
     * Departments that are allowed to access this room (cross-open).
     */
    public function openDepartments()
    {
        return $this->belongsToMany(Department::class, 'department_room');
    }

    /**
     * @return BelongsTo<Department, $this>
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function scopeBookableForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin()) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('department_id', $user->department_id)
                ->orWhere(function (Builder $openBuilder) use ($user): void {
                    $openBuilder
                        ->where('is_open_access', true)
                        ->where(function (Builder $accessBuilder) use ($user): void {
                            $accessBuilder
                                ->where('open_access_all', true)
                                ->orWhereHas('openDepartments', fn (Builder $query) => $query->where('departments.id', $user->department_id));
                        });
                });
        });
    }

    public function isBookableBy(User $user): bool
    {
        if ($user->isAdmin()) {
            return true;
        }

        if ((int) $this->department_id === (int) $user->department_id) {
            return true;
        }

        if (! $this->is_open_access) {
            return false;
        }

        if ($this->open_access_all) {
            return true;
        }

        return $this->openDepartments()
            ->where('departments.id', $user->department_id)
            ->exists();
    }
}
