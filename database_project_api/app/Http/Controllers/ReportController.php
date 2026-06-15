<?php

namespace App\Http\Controllers;

use App\Models\Reservation;
use App\Models\Payment;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReportController extends Controller
{
    public function reservationReport(): JsonResponse
    {
        return response()->json([
            'rooms' => $this->roomOptions(),
            'statuses' => $this->reservationStatuses(),
        ]);
    }

    public function monthlyReservations(Request $request): JsonResponse
    {
        $filters = $this->validatedReservationFilters($request);
        [$startDate, $endDate] = $this->dateRange($filters);

        $reservations = $this->reservationQuery($filters, $startDate, $endDate)
            ->with(['room', 'timeSlot'])
            ->get();

        $monthly = $this->monthBuckets($startDate, $endDate)
            ->map(function (array $month) use ($reservations): array {
                $count = $reservations
                    ->filter(fn (Reservation $reservation): bool => $reservation->reservation_date?->format('Y-m') === $month['key'])
                    ->count();

                return [
                    ...$month,
                    'count' => $count,
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => $filters['status'] ?? '',
                'room_id' => $filters['room_id'] ?? '',
            ],
            'summary' => [
                'total' => $reservations->count(),
                'success' => $reservations->where('reservation_status', 'success')->count(),
                'pending' => $reservations->where('reservation_status', 'pending')->count(),
                'cancelled' => $reservations->where('reservation_status', 'cancelled')->count(),
                'rejected' => $reservations->where('reservation_status', 'rejected')->count(),
            ],
            'monthly' => $monthly,
            'status_totals' => $reservations
                ->groupBy('reservation_status')
                ->map(fn ($items, string $status): array => [
                    'status' => $status,
                    'count' => $items->count(),
                ])
                ->values(),
            'room_totals' => $reservations
                ->groupBy('room_id')
                ->map(fn ($items): array => [
                    'room_id' => $items->first()->room_id,
                    'room_name' => $items->first()->room?->name ?? '未知空間',
                    'count' => $items->count(),
                ])
                ->sortByDesc('count')
                ->values(),
        ]);
    }

    public function roomUsage(Request $request): JsonResponse
    {
        $filters = $this->validatedReservationFilters($request);
        [$startDate, $endDate] = $this->dateRange($filters);

        $rows = $this->reservationQuery($filters, $startDate, $endDate)
            ->selectRaw('room_id, COUNT(*) as reservation_count')
            ->where('reservation_status', 'success')
            ->groupBy('room_id')
            ->orderByDesc('reservation_count')
            ->with('room')
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'room_id' => $reservation->room_id,
                'room_name' => $reservation->room?->name ?? '未知空間',
                'building' => $reservation->room?->building,
                'type' => $reservation->room?->type,
                'reservation_count' => (int) $reservation->reservation_count,
            ])
            ->values();

        return response()->json([
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'status' => 'success',
                'room_id' => $filters['room_id'] ?? '',
            ],
            'data' => $rows,
        ]);
    }

    public function payments(Request $request): JsonResponse
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_status' => ['nullable', 'string', 'in:unpaid,paid,cancelled'],
            'room_id' => ['nullable', 'integer', 'exists:Room,id'],
        ]);
        [$startDate, $endDate] = $this->dateRange($filters);

        $payments = Payment::query()
            ->with(['reservation.room', 'reservation.user', 'reservation.timeSlot'])
            ->whereHas('reservation', fn (Builder $query) => $query
                ->whereDate('reservation_date', '>=', $startDate->toDateString())
                ->whereDate('reservation_date', '<=', $endDate->toDateString())
                ->when($filters['payment_status'] ?? null, fn (Builder $statusQuery, string $status) => $statusQuery->where('payment_status', $status))
                ->when($filters['room_id'] ?? null, fn (Builder $roomQuery, int|string $roomId) => $roomQuery->where('room_id', $roomId)))
            ->latest('created_at')
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'payment_status' => $payment->reservation?->payment_status,
                'reservation_id' => $payment->reservation_id,
                'created_at' => $payment->created_at?->toDateTimeString(),
                'reservation' => $payment->reservation ? [
                    'id' => $payment->reservation->id,
                    'start_time' => $payment->reservation->start_time?->toDateTimeString(),
                    'end_time' => $payment->reservation->end_time?->toDateTimeString(),
                    'reservation_status' => $payment->reservation->reservation_status,
                    'room' => $payment->reservation->room ? [
                        'id' => $payment->reservation->room->id,
                        'name' => $payment->reservation->room->name,
                        'building' => $payment->reservation->room->building,
                        'type' => $payment->reservation->room->type,
                    ] : null,
                    'user' => $payment->reservation->user ? [
                        'id' => $payment->reservation->user->id,
                        'name' => $payment->reservation->user->name,
                        'email' => $payment->reservation->user->email,
                    ] : null,
                ] : null,
            ])
            ->values();

        return response()->json([
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'payment_status' => $filters['payment_status'] ?? '',
                'room_id' => $filters['room_id'] ?? '',
            ],
            'summary' => [
                'count' => $payments->count(),
                'amount' => $payments->sum('amount'),
                'paid_amount' => $payments->where('payment_status', 'paid')->sum('amount'),
                'unpaid_amount' => $payments->where('payment_status', 'unpaid')->sum('amount'),
                'cancelled_amount' => $payments->where('payment_status', 'cancelled')->sum('amount'),
            ],
            'data' => $payments,
        ]);
    }

    private function reservationQuery(array $filters, Carbon $startDate, Carbon $endDate): Builder
    {
        $query = Reservation::query()
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('reservation_status', $status));

        return $this->applyReservationDateAndRoomFilters($query, $filters, $startDate, $endDate);
    }

    private function applyReservationDateAndRoomFilters(Builder $query, array $filters, Carbon $startDate, Carbon $endDate): Builder
    {
        return $query
            ->whereDate('reservation_date', '>=', $startDate->toDateString())
            ->whereDate('reservation_date', '<=', $endDate->toDateString())
            ->when($filters['room_id'] ?? null, fn (Builder $query, int|string $roomId) => $query->where('room_id', $roomId));
    }

    private function validatedReservationFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:pending,success,cancelled,rejected'],
            'room_id' => ['nullable', 'integer', 'exists:Room,id'],
        ]);
    }

    private function dateRange(array $filters): array
    {
        $startDate = filled($filters['start_date'] ?? null)
            ? Carbon::parse($filters['start_date'])->startOfDay()
            : now()->startOfYear();
        $endDate = filled($filters['end_date'] ?? null)
            ? Carbon::parse($filters['end_date'])->endOfDay()
            : now()->endOfYear();

        return [$startDate, $endDate];
    }

    private function monthBuckets(Carbon $startDate, Carbon $endDate)
    {
        $months = collect();
        $cursor = $startDate->copy()->startOfMonth();
        $lastMonth = $endDate->copy()->startOfMonth();

        while ($cursor <= $lastMonth) {
            $months->push([
                'key' => $cursor->format('Y-m'),
                'label' => $cursor->format('Y/m'),
            ]);
            $cursor->addMonth();
        }

        return $months;
    }

    private function roomOptions(): array
    {
        return Room::query()
            ->orderBy('room_name')
            ->get()
            ->map(fn (Room $room): array => [
                'id' => $room->id,
                'name' => $room->name,
                'building' => $room->building,
                'type' => $room->type,
            ])
            ->values()
            ->all();
    }

    private function reservationStatuses(): array
    {
        return [
            ['value' => 'pending', 'label' => '待審核'],
            ['value' => 'success', 'label' => '已核准'],
            ['value' => 'cancelled', 'label' => '已取消'],
            ['value' => 'rejected', 'label' => '已拒絕'],
        ];
    }
}
