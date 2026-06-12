<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        $groups = DB::table('Payment as payments')
            ->join('Reservation as reservations', 'payments.reservation_id', '=', 'reservations.id')
            ->whereNotNull('reservations.reservation_group_id')
            ->select('reservations.reservation_group_id')
            ->groupBy('reservations.reservation_group_id')
            ->havingRaw('COUNT(payments.id) > 1')
            ->pluck('reservation_group_id');

        foreach ($groups as $groupId) {
            $payments = DB::table('Payment as payments')
                ->join('Reservation as reservations', 'payments.reservation_id', '=', 'reservations.id')
                ->where('reservations.reservation_group_id', $groupId)
                ->orderBy('reservations.reservation_date')
                ->orderBy('reservations.time_slot_id')
                ->select([
                    'payments.id',
                    'payments.amount',
                    'payments.reservation_id',
                    'payments.created_at',
                    'payments.updated_at',
                    'reservations.payment_status',
                ])
                ->get();

            if ($payments->count() <= 1) {
                continue;
            }

            $representative = $payments->first();
            $amount = $payments->sum('amount');
            $status = $payments->contains(fn ($payment): bool => $payment->payment_status === 'unpaid')
                ? 'unpaid'
                : 'paid';

            DB::table('Payment')
                ->where('id', $representative->id)
                ->update([
                    'amount' => $amount,
                    'updated_at' => now(),
                ]);

            DB::table('Reservation')
                ->where('reservation_group_id', $groupId)
                ->update(['payment_status' => $status]);

            DB::table('Payment')
                ->whereIn('id', $payments->pluck('id')->reject(fn ($id): bool => (int) $id === (int) $representative->id))
                ->delete();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        //
    }
};
