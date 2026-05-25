<?php

namespace App\Http\Controllers;

use App\Models\Approval;
use App\Models\Payment;
use App\Models\Reservation;
use App\Models\Room;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Admin/DashboardPage');
    }

    public function summary(Request $request): JsonResponse
    {
        $user = $request->user();
        $roles = $user->roles()->pluck('role_type')->values()->all();

        $canViewOperations = $this->hasAnyRole($roles, ['管理員', '行政人員']);
        $canReviewApprovals = $this->hasAnyRole($roles, ['管理員', '行政人員']);
        $canViewRevenue = $this->hasAnyRole($roles, ['管理員', '行政人員']);
        $canViewManagementStats = $canViewOperations;
        $canManageUsers = in_array('管理員', $roles, true);

        $todayEnd = today()->endOfDay();
        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $reservationScope = $this->reservationScope($user, $canViewOperations || $canReviewApprovals);

        return response()->json([
            'roles' => $roles,
            'permissions' => [
                'can_view_operations' => $canViewOperations,
                'can_review_approvals' => $canReviewApprovals,
                'can_view_revenue' => $canViewRevenue,
                'can_view_management_stats' => $canViewManagementStats,
                'can_manage_users' => $canManageUsers,
            ],
            'today' => [
                'reservations' => (clone $reservationScope)->where('start_time', '<=', $todayEnd)->count(),
                'pending' => (clone $reservationScope)->where('start_time', '<=', $todayEnd)->where('reservation_status', 'pending')->count(),
                'approved' => (clone $reservationScope)->where('start_time', '<=', $todayEnd)->where('reservation_status', 'success')->count(),
                'cancelled' => (clone $reservationScope)->where('start_time', '<=', $todayEnd)->where('reservation_status', 'cancelled')->count(),
            ],
            'tasks' => [
                'pending_reservations' => $canReviewApprovals
                    ? Reservation::where('reservation_status', 'pending')->count()
                    : (clone $reservationScope)->where('reservation_status', 'pending')->count(),
                'unpaid_orders' => $canViewRevenue
                    ? Payment::where('payment_status', 'unpaid')->count()
                    : $this->scopedUnpaidPaymentCount($reservationScope),
                'upcoming_reservations' => (clone $reservationScope)
                    ->whereIn('reservation_status', ['pending', 'success'])
                    ->whereBetween('start_time', [$now, $now->copy()->addDay()])
                    ->count(),
                // Provide lists for frontend display
                'unpaid_orders_list' => $this->unpaidOrdersList($reservationScope, $canViewRevenue),
                'upcoming_reservations_list' => $this->upcomingReservationsList($reservationScope, $now),
                'inactive_accounts' => $canManageUsers
                    ? User::where('is_active', false)->count()
                    : null,
            ],
            'month' => [
                'borrow_count' => $canViewManagementStats
                    ? (clone $reservationScope)
                        ->whereBetween('start_time', [$monthStart, $monthEnd])
                        ->count()
                    : null,
                'revenue' => $canViewRevenue
                    ? $this->paymentAmountForMonth($monthStart, $monthEnd)
                    : null,
                'unpaid_amount' => $canViewRevenue
                    ? $this->paymentAmountForMonth($monthStart, $monthEnd, 'unpaid')
                    : null,
                'top_room' => $canViewManagementStats
                    ? $this->topRoomForMonth($reservationScope, $monthStart, $monthEnd)
                    : null,
            ],
            'recent' => [
                'reservations' => $this->recentReservations($reservationScope),
                'payments' => $canViewRevenue ? $this->recentPayments() : [],
                'approvals' => $canReviewApprovals ? $this->recentApprovals() : [],
            ],
            'room_rankings' => $canViewManagementStats ? $this->roomRankings($reservationScope) : [],
        ]);
    }

    private function reservationScope(User $user, bool $canViewAll): Builder
    {
        return Reservation::query()
            ->when(! $canViewAll, fn (Builder $query) => $query->where('user_id', $user->id));
    }

    private function paymentAmountForMonth(Carbon $monthStart, Carbon $monthEnd, ?string $status = null): int
    {
        return (int) Payment::query()
            ->whereHas('reservation', fn (Builder $query) => $query->whereBetween('start_time', [$monthStart, $monthEnd]))
            ->when($status, fn (Builder $query, string $status) => $query->where('payment_status', $status))
            ->sum('amount');
    }

    private function scopedUnpaidPaymentCount(Builder $reservationScope): int
    {
        $reservationIds = (clone $reservationScope)->select('id');

        return Payment::query()
            ->where('payment_status', 'unpaid')
            ->whereIn('reservation_id', $reservationIds)
            ->count();
    }

    private function topRoomForMonth(Builder $reservationScope, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $row = (clone $reservationScope)
            ->selectRaw('room_id, COUNT(*) as borrow_count')
            ->whereBetween('start_time', [$monthStart, $monthEnd])
            ->groupBy('room_id')
            ->orderByDesc('borrow_count')
            ->first();

        if (! $row) {
            return null;
        }

        $room = Room::find($row->room_id);

        return [
            'id' => $room?->id,
            'name' => $room?->name ?? '未知空間',
            'building' => $room?->building,
            'type' => $room?->type,
            'borrow_count' => (int) $row->borrow_count,
        ];
    }

    private function recentReservations(Builder $reservationScope): array
    {
        return (clone $reservationScope)
            ->with(['room', 'user'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'start_time' => $reservation->start_time?->toDateTimeString(),
                'end_time' => $reservation->end_time?->toDateTimeString(),
                'status' => $reservation->reservation_status,
                'room_name' => $reservation->room?->name ?? '未知空間',
                'room_building' => $reservation->room?->building,
                'user_name' => $reservation->user?->name ?? $reservation->user?->email,
            ])
            ->all();
    }

    private function recentPayments(): array
    {
        return Payment::with(['reservation.room', 'reservation.user'])
            ->latest('created_at')
            ->limit(5)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'amount' => $payment->amount,
                'status' => $payment->payment_status,
                'room_name' => $payment->reservation?->room?->name ?? '未知空間',
                'user_name' => $payment->reservation?->user?->name ?? $payment->reservation?->user?->email,
                'created_at' => $payment->created_at?->toDateTimeString(),
            ])
            ->all();
    }

    private function recentApprovals(): array
    {
        return Approval::with(['reservation.room', 'reservation.user', 'approver'])
            ->latest('decision_time')
            ->limit(5)
            ->get()
            ->map(fn (Approval $approval): array => [
                'id' => $approval->id,
                'decision' => $approval->decision,
                'decision_time' => $approval->decision_time?->toDateTimeString(),
                'room_name' => $approval->reservation?->room?->name ?? '未知空間',
                'user_name' => $approval->reservation?->user?->name ?? $approval->reservation?->user?->email,
                'approver_name' => $approval->approver?->name ?? $approval->approver?->email,
            ])
            ->all();
    }

    private function roomRankings(Builder $reservationScope): array
    {
        return (clone $reservationScope)
            ->selectRaw('room_id, COUNT(*) as borrow_count')
            ->where('start_time', '>=', now()->copy()->subDays(30))
            ->groupBy('room_id')
            ->orderByDesc('borrow_count')
            ->limit(5)
            ->get()
            ->map(function ($row): array {
                $room = Room::find($row->room_id);

                return [
                    'id' => $room?->id,
                    'name' => $room?->name ?? '未知空間',
                    'building' => $room?->building,
                    'type' => $room?->type,
                    'borrow_count' => (int) $row->borrow_count,
                ];
            })
            ->all();
    }

    private function unpaidOrdersList(Builder $reservationScope, bool $canViewRevenue): array
    {
        $reservationIds = (clone $reservationScope)->select('id');

        $payments = Payment::with(['reservation.room'])
            ->where('payment_status', 'unpaid')
            ->when(! $canViewRevenue, fn ($query) => $query->whereIn('reservation_id', $reservationIds))
            ->latest('created_at')
            ->limit(10)
            ->get()
            ->map(fn (Payment $payment): array => [
                'id' => $payment->id,
                'reservation_id' => $payment->reservation_id,
                'amount' => $payment->amount,
                'room' => $payment->reservation
                    ? [
                        'id' => $payment->reservation->room?->id,
                        'name' => $payment->reservation->room?->name,
                        'building' => $payment->reservation->room?->building,
                    ]
                    : null,
                'start_time' => $payment->reservation?->start_time?->toDateTimeString(),
            ])
            ->all();

        return $payments;
    }

    private function upcomingReservationsList(Builder $reservationScope, Carbon $now): array
    {
        return (clone $reservationScope)
            ->with('room')
            ->whereIn('reservation_status', ['pending', 'success'])
            ->whereBetween('start_time', [$now, $now->copy()->addDay()])
            ->orderBy('start_time')
            ->limit(10)
            ->get()
            ->map(fn (Reservation $reservation): array => [
                'id' => $reservation->id,
                'date' => $reservation->start_time?->toDateString(),
                'start_time' => $reservation->start_time?->toDateTimeString(),
                'end_time' => $reservation->end_time?->toDateTimeString(),
                'room' => $reservation->room
                    ? [
                        'id' => $reservation->room->id,
                        'name' => $reservation->room->name,
                        'building' => $reservation->room->building,
                    ]
                    : null,
            ])
            ->all();
    }

    private function hasAnyRole(array $roles, array $allowedRoles): bool
    {
        return collect($allowedRoles)->contains(fn (string $role): bool => in_array($role, $roles, true));
    }
}
