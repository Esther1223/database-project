<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('Reservation', 'payment_status')) {
            Schema::table('Reservation', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->after('reservation_status');
            });
        }

        if (Schema::hasColumn('Payment', 'payment_status')) {
            DB::table('Payment')
                ->select(['reservation_id', 'payment_status'])
                ->whereIn('payment_status', ['unpaid', 'paid'])
                ->orderBy('id')
                ->each(function (object $payment): void {
                    DB::table('Reservation')
                        ->where('id', $payment->reservation_id)
                        ->update(['payment_status' => $payment->payment_status]);
                });

            Schema::table('Payment', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }

        if (Schema::hasColumn('Room', 'hourly_rate')) {
            Schema::table('Room', function (Blueprint $table): void {
                $table->dropColumn('hourly_rate');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('Room', 'hourly_rate')) {
            Schema::table('Room', function (Blueprint $table): void {
                $table->integer('hourly_rate')->default(0)->after('information');
            });
        }

        if (! Schema::hasColumn('Payment', 'payment_status')) {
            Schema::table('Payment', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->after('amount');
            });

            DB::table('Reservation')
                ->select(['id', 'payment_status'])
                ->whereIn('payment_status', ['unpaid', 'paid'])
                ->orderBy('id')
                ->each(function (object $reservation): void {
                    DB::table('Payment')
                        ->where('reservation_id', $reservation->id)
                        ->update(['payment_status' => $reservation->payment_status]);
                });
        }

        if (Schema::hasColumn('Reservation', 'payment_status')) {
            Schema::table('Reservation', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }
    }
};
