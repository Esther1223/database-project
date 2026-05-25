<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class ReportController extends Controller
{
    public function reservationReport(): Response
    {
        return Inertia::render('Admin/Reports/ReservationReportPage', [
            'rooms' => $this->roomOptions(),
            'statuses' => $this->reservationStatuses(),
        ]);
    }

    public function revenueReport(): Response
    {
        return Inertia::render('Admin/Reports/RevenueReportPage', [
            'rooms' => $this->roomOptions(),
            'paymentStatuses' => $this->paymentStatuses(),
        ]);
    }

    public function monthlyReservations(Request $request): JsonResponse
    {
        $filters = $this->validatedReservationFilters($request);
        [$startDate, $endDate] = $this->dateRange($filters);

        $reservations = $this->reservationQuery($filters, $startDate, $endDate)
            ->with(['room'])
            ->get();

        $monthly = $this->monthBuckets($startDate, $endDate)
            ->map(function (array $month) use ($reservations): array {
                $count = $reservations
                    ->filter(fn (Reservation $reservation): bool => $reservation->start_time?->format('Y-m') === $month['key'])
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

    public function monthlyRevenue(Request $request): JsonResponse
    {
        $filters = $this->validatedRevenueFilters($request);
        [$startDate, $endDate] = $this->dateRange($filters);

        $payments = Payment::query()
            ->with(['reservation.room', 'reservation.user'])
            ->whereHas('reservation', fn (Builder $query) => $this->applyReservationDateAndRoomFilters($query, $filters, $startDate, $endDate))
            ->when($filters['payment_status'] ?? null, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->get();

        $monthly = $this->monthBuckets($startDate, $endDate)
            ->map(function (array $month) use ($payments): array {
                $monthPayments = $payments->filter(
                    fn (Payment $payment): bool => $payment->reservation?->start_time?->format('Y-m') === $month['key'],
                );

                return [
                    ...$month,
                    'amount' => $monthPayments->sum('amount'),
                    'paid_amount' => $monthPayments->where('payment_status', 'paid')->sum('amount'),
                    'unpaid_amount' => $monthPayments->where('payment_status', 'unpaid')->sum('amount'),
                    'count' => $monthPayments->count(),
                ];
            })
            ->values();

        return response()->json([
            'filters' => [
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'payment_status' => $filters['payment_status'] ?? '',
                'room_id' => $filters['room_id'] ?? '',
            ],
            'summary' => [
                'total_amount' => $payments->sum('amount'),
                'paid_amount' => $payments->where('payment_status', 'paid')->sum('amount'),
                'unpaid_amount' => $payments->where('payment_status', 'unpaid')->sum('amount'),
                'payment_count' => $payments->count(),
            ],
            'monthly' => $monthly,
            'status_totals' => $payments
                ->groupBy('payment_status')
                ->map(fn ($items, string $status): array => [
                    'status' => $status,
                    'amount' => $items->sum('amount'),
                    'count' => $items->count(),
                ])
                ->values(),
            'room_totals' => $payments
                ->groupBy(fn (Payment $payment): int|string => $payment->reservation?->room_id ?? 'unknown')
                ->map(fn ($items): array => [
                    'room_id' => $items->first()->reservation?->room_id,
                    'room_name' => $items->first()->reservation?->room?->name ?? '未知空間',
                    'amount' => $items->sum('amount'),
                    'count' => $items->count(),
                ])
                ->sortByDesc('amount')
                ->values(),
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
            ->whereDate('start_time', '>=', $startDate->toDateString())
            ->whereDate('start_time', '<=', $endDate->toDateString())
            ->when($filters['room_id'] ?? null, fn (Builder $query, int|string $roomId) => $query->where('room_id', $roomId));
    }

    private function validatedReservationFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'status' => ['nullable', 'string', 'in:pending,success,cancelled,rejected'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
        ]);
    }

    private function validatedRevenueFilters(Request $request): array
    {
        return $request->validate([
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'payment_status' => ['nullable', 'string', 'in:paid,unpaid'],
            'room_id' => ['nullable', 'integer', 'exists:rooms,id'],
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

    private function paymentStatuses(): array
    {
        return [
            ['value' => 'paid', 'label' => '已付款'],
            ['value' => 'unpaid', 'label' => '未付款'],
        ];
    }
}
