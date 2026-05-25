<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PaymentController extends Controller
{
    /**
     * Render the payment management page.
     */
    public function index(): Response
    {
        return Inertia::render('Admin/Payments/PaymentListPage');
    }

    /**
     * Return payment list for admin reconciliation.
     */
    public function list(): JsonResponse
    {
        $payments = Payment::with(['reservation.room', 'reservation.user'])
            ->latest('created_at')
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_status' => $payment->payment_status,
                'created_at' => $payment->created_at?->toDateTimeString(),
                'updated_at' => $payment->updated_at?->toDateTimeString(),
                'reservation' => $payment->reservation
                    ? [
                        'id' => $payment->reservation->id,
                        'start_time' => $payment->reservation->start_time?->toDateTimeString(),
                        'end_time' => $payment->reservation->end_time?->toDateTimeString(),
                        'reservation_status' => $payment->reservation->reservation_status,
                        'room' => $payment->reservation->room
                            ? [
                                'id' => $payment->reservation->room->id,
                                'name' => $payment->reservation->room->name,
                                'type' => $payment->reservation->room->type,
                                'building' => $payment->reservation->room->building,
                                'rate' => $payment->reservation->room->rate,
                            ]
                            : null,
                        'user' => $payment->reservation->user
                            ? [
                                'id' => $payment->reservation->user->id,
                                'name' => $payment->reservation->user->name,
                                'email' => $payment->reservation->user->email,
                            ]
                            : null,
                    ]
                    : null,
            ]);

        return response()->json(['data' => $payments]);
    }

    /**
     * Update payment status.
     */
    public function updateStatus(Request $request, Payment $payment): JsonResponse
    {
        $validated = $request->validate([
            'payment_status' => ['required', 'string', 'in:paid,unpaid'],
        ]);

        $payment->update([
            'payment_status' => $validated['payment_status'],
        ]);

        return response()->json([
            'message' => '付款狀態已更新',
        ]);
    }
}
