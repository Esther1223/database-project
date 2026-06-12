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

    protected $table = 'Room';

    protected $fillable = [
        'name',
        'type',
        'room_name',
        'room_type',
        'capacity',
        'building',
        'affiliation_id',
        'information',
        'need_approval',
        'is_open_access',
        'open_access_all',
    ];

    protected function casts(): array
    {
        return [
            'capacity' => 'integer',
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

    /**
     * @return HasMany<Reservation, $this>
     */
    public function reservations(): HasMany
    {
        return $this->hasMany(Reservation::class);
    }

    /**
     * @return HasMany<TimeSlot, $this>
     */
    public function timeSlots(): HasMany
    {
        return $this->hasMany(TimeSlot::class);
    }

    /**
     * Affiliations that are allowed to access this room (cross-open).
     */
    public function openAffiliations()
    {
        return $this->belongsToMany(Affiliation::class, 'Allow_aff');
    }

    /**
     * @return BelongsTo<Affiliation, $this>
     */
    public function affiliation(): BelongsTo
    {
        return $this->belongsTo(Affiliation::class);
    }

    public function scopeBookableForUser(Builder $query, User $user): Builder
    {
        if ($user->isAdmin() || $user->hasRole('行政人員')) {
            return $query;
        }

        return $query->where(function (Builder $builder) use ($user): void {
            $builder
                ->where('affiliation_id', $user->affiliation_id)
                ->orWhere(function (Builder $openBuilder) use ($user): void {
                    $openBuilder
                        ->where('is_open_access', true)
                        ->where(function (Builder $accessBuilder) use ($user): void {
                            $accessBuilder
                                ->where('open_access_all', true)
                                ->orWhereHas('openAffiliations', fn (Builder $query) => $query->where('Affiliation.id', $user->affiliation_id));
                        });
                });
        });
    }

    public function isBookableBy(User $user): bool
    {
        if ($user->isAdmin() || $user->hasRole('行政人員')) {
            return true;
        }

        if ((int) $this->affiliation_id === (int) $user->affiliation_id) {
            return true;
        }

        if (! $this->is_open_access) {
            return false;
        }

        if ($this->open_access_all) {
            return true;
        }

        return $this->openAffiliations()
            ->where('Affiliation.id', $user->affiliation_id)
            ->exists();
    }
}
