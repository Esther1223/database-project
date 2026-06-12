<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('reservations', 'payment_status')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->after('reservation_status');
            });
        }

        if (Schema::hasColumn('payments', 'payment_status')) {
            DB::table('payments')
                ->select(['reservation_id', 'payment_status'])
                ->whereIn('payment_status', ['unpaid', 'paid'])
                ->orderBy('id')
                ->each(function (object $payment): void {
                    DB::table('reservations')
                        ->where('id', $payment->reservation_id)
                        ->update(['payment_status' => $payment->payment_status]);
                });

            Schema::table('payments', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }

        if (Schema::hasColumn('rooms', 'hourly_rate')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->dropColumn('hourly_rate');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('rooms', 'hourly_rate')) {
            Schema::table('rooms', function (Blueprint $table): void {
                $table->integer('hourly_rate')->default(0)->after('information');
            });
        }

        if (! Schema::hasColumn('payments', 'payment_status')) {
            Schema::table('payments', function (Blueprint $table): void {
                $table->string('payment_status')->default('unpaid')->after('amount');
            });

            DB::table('reservations')
                ->select(['id', 'payment_status'])
                ->whereIn('payment_status', ['unpaid', 'paid'])
                ->orderBy('id')
                ->each(function (object $reservation): void {
                    DB::table('payments')
                        ->where('reservation_id', $reservation->id)
                        ->update(['payment_status' => $reservation->payment_status]);
                });
        }

        if (Schema::hasColumn('reservations', 'payment_status')) {
            Schema::table('reservations', function (Blueprint $table): void {
                $table->dropColumn('payment_status');
            });
        }
    }
};
