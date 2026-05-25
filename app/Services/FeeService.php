<?php

namespace App\Services;

use App\Models\Reservation;
use Carbon\Carbon;

class FeeService
{
    public function calculateAmount(Reservation $reservation): int
    {
        $reservation->loadMissing('room');
        $room = $reservation->room;

        if (!$room || $room->rate <= 0) {
            return 0;
        }

        $start = Carbon::parse($reservation->start_time);
        $end = Carbon::parse($reservation->end_time);
        $minutes = $start->diffInMinutes($end, false);

        if ($minutes <= 0) {
            return 0;
        }

        $hours = (int) ceil($minutes / 60);

        return $hours * (int) $room->rate;
    }
}
