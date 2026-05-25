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
        $canReviewApprovals = $this->hasAnyRole($roles, ['管理員', '行政人員', '教授']);
        $canViewRevenue = $this->hasAnyRole($roles, ['管理員', '行政人員']);
        $canManageUsers = in_array('管理員', $roles, true);

        $today = today();
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
                'can_manage_users' => $canManageUsers,
            ],
            'today' => [
                'reservations' => (clone $reservationScope)->whereDate('start_time', $today)->count(),
                'pending' => (clone $reservationScope)->whereDate('start_time', $today)->where('reservation_status', 'pending')->count(),
                'approved' => (clone $reservationScope)->whereDate('start_time', $today)->where('reservation_status', 'success')->count(),
                'cancelled' => (clone $reservationScope)->whereDate('start_time', $today)->where('reservation_status', 'cancelled')->count(),
            ],
            'tasks' => [
                'pending_reservations' => $canReviewApprovals
                    ? Reservation::where('reservation_status', 'pending')->count()
                    : null,
                'unpaid_orders' => $canViewRevenue
                    ? Payment::where('payment_status', 'unpaid')->count()
                    : null,
                'upcoming_reservations' => (clone $reservationScope)
                    ->whereIn('reservation_status', ['pending', 'success'])
                    ->whereBetween('start_time', [$now, $now->copy()->addDay()])
                    ->count(),
                'inactive_accounts' => $canManageUsers
                    ? User::where('is_active', false)->count()
                    : null,
            ],
            'month' => [
                'borrow_count' => (clone $reservationScope)
                    ->whereBetween('start_time', [$monthStart, $monthEnd])
                    ->count(),
                'revenue' => $canViewRevenue
                    ? $this->paymentAmountForMonth($monthStart, $monthEnd)
                    : null,
                'unpaid_amount' => $canViewRevenue
                    ? $this->paymentAmountForMonth($monthStart, $monthEnd, 'unpaid')
                    : null,
                'top_room' => $this->topRoomForMonth($reservationScope, $monthStart, $monthEnd),
            ],
            'recent' => [
                'reservations' => $this->recentReservations($reservationScope),
                'payments' => $canViewRevenue ? $this->recentPayments() : [],
                'approvals' => $canReviewApprovals ? $this->recentApprovals() : [],
            ],
            'room_rankings' => $this->roomRankings($reservationScope),
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

    private function hasAnyRole(array $roles, array $allowedRoles): bool
    {
        return collect($allowedRoles)->contains(fn (string $role): bool => in_array($role, $roles, true));
    }
}
