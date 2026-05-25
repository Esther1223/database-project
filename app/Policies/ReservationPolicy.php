<?php

namespace App\Policies;

use App\Models\Reservation;
use App\Models\User;

class ReservationPolicy
{
    /**
     * Give administrators full access.
     */
    public function before(User $user, string $ability): ?bool
    {
        return $user->isAdmin() ? true : null;
    }

    public function viewAny(User $user): bool
    {
        return $user->hasRole('行政人員') || $user->hasRole('教授') || $user->hasRole('學生');
    }

    public function view(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id
            || $user->hasRole('行政人員')
            || $user->hasRole('教授');
    }

    public function create(User $user): bool
    {
        return $user->isActive() && ($user->hasRole('行政人員') || $user->hasRole('教授') || $user->hasRole('學生'));
    }

    public function update(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id
            && in_array($reservation->reservation_status, ['pending', 'success'], true);
    }

    public function delete(User $user, Reservation $reservation): bool
    {
        return $reservation->user_id === $user->id
            && in_array($reservation->reservation_status, ['pending', 'success'], true);
    }

    public function approve(User $user, Reservation $reservation): bool
    {
        return $user->hasRole('行政人員');
    }
}
