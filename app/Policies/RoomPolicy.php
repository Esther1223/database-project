<?php

namespace App\Policies;

use App\Models\Room;
use App\Models\User;

class RoomPolicy
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

    public function view(User $user, Room $room): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool
    {
        return $user->hasRole('行政人員');
    }

    public function update(User $user, Room $room): bool
    {
        return $user->hasRole('行政人員');
    }

    public function delete(User $user, Room $room): bool
    {
        return $user->hasRole('行政人員');
    }
}
