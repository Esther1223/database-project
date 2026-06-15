<?php

namespace App\Services;

use App\Models\Reservation;

class FeeService
{
    public function calculateAmount(Reservation $reservation): int
    {
        $reservation->loadMissing('timeSlot');
        $timeSlot = $reservation->timeSlot;

        if (! $timeSlot) {
            return 0;
        }

        return (int) $timeSlot->price;
    }
}
