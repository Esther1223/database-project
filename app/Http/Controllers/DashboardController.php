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
use Illuminate\Support\Collection;
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

        $canReserve = $this->hasAnyRole($roles, ['行政人員', '教授', '學生']);
        $canManageRooms = $this->hasAnyRole($roles, ['管理員', '行政人員']);
        $canViewOperations = $this->hasAnyRole($roles, ['行政人員']);
        $canReviewApprovals = $this->hasAnyRole($roles, ['行政人員']);
        $canViewRevenue = $this->hasAnyRole($roles, ['行政人員']);
        $canViewManagementStats = $canViewOperations;
        $canManageUsers = in_array('管理員', $roles, true);

        $now = now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        $reservationScope = $this->reservationScope($user, $canViewOperations || $canReviewApprovals);
        $personalReservationScope = $this->reservationScope($user, false);
        $reservationGroupCounts = $canReserve || $canViewOperations
            ? $this->reservationGroupCounts($reservationScope)
            : ['reservations' => null, 'pending' => null, 'approved' => null, 'cancelled' => null];

        return response()->json([
            'roles' => $roles,
            'permissions' => [
                'can_reserve' => $canReserve,
                'can_manage_rooms' => $canManageRooms,
                'can_view_operations' => $canViewOperations,
                'can_review_approvals' => $canReviewApprovals,
                'can_view_revenue' => $canViewRevenue,
                'can_view_management_stats' => $canViewManagementStats,
                'can_manage_users' => $canManageUsers,
            ],
            'system' => [
                'rooms' => $canManageRooms ? Room::count() : null,
                'users' => $canManageUsers ? User::count() : null,
                'inactive_accounts' => $canManageUsers ? User::where('is_active', false)->count() : null,
                'pending_accounts' => $canManageUsers ? User::where('is_active', false)->count() : null,
            ],
            'today' => [
                'reservations' => $reservationGroupCounts['reservations'],
                'pending' => $reservationGroupCounts['pending'],
                'approved' => $reservationGroupCounts['approved'],
                'cancelled' => $reservationGroupCounts['cancelled'],
            ],
            'tasks' => [
                'pending_reservations' => $canReviewApprovals
                    ? Reservation::where('reservation_status', 'pending')->count()
                    : null,
                'unpaid_orders' => $canViewRevenue
                    ? Payment::whereHas('reservation', fn (Builder $query) => $query->where('payment_status', 'unpaid'))->count()
                    : null,
                'upcoming_reservations' => $canReserve
                    ? (clone $personalReservationScope)
                        ->with('timeSlot')
                        ->whereIn('reservation_status', ['pending', 'success'])
                        ->whereDate('reservation_date', '>=', $now->toDateString())
                        ->whereDate('reservation_date', '<=', $now->copy()->addDay()->toDateString())
                        ->get()
                        ->filter(fn (Reservation $reservation): bool => $reservation->start_time >= $now && $reservation->start_time <= $now->copy()->addDay())
                        ->count()
                    : null,
                'unpaid_orders_list' => $canViewRevenue ? $this->unpaidOrdersList($reservationScope, true) : [],
                'upcoming_reservations_list' => $canReserve ? $this->upcomingReservationsList($personalReservationScope, $now) : [],
                'inactive_accounts' => $canManageUsers ? User::where('is_active', false)->count() : null,
            ],
            'month' => [
                'borrow_count' => $canViewManagementStats
                    ? (clone $reservationScope)
                        ->whereDate('reservation_date', '>=', $monthStart->toDateString())
                        ->whereDate('reservation_date', '<=', $monthEnd->toDateString())
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
                'reservations' => ($canReserve || $canViewOperations) ? $this->recentReservations($reservationScope) : [],
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

    private function reservationGroupCounts(Builder $reservationScope): array
    {
        $statuses = (clone $reservationScope)
            ->get(['id', 'reservation_group_id', 'reservation_status'])
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservation_group_id ?: (string) $reservation->id)
            ->map(fn (Collection $group): string => $this->groupStatus($group));

        return [
            'reservations' => $statuses->count(),
            'pending' => $statuses->filter(fn (string $status): bool => $status === 'pending')->count(),
            'approved' => $statuses->filter(fn (string $status): bool => $status === 'success')->count(),
            'cancelled' => $statuses->filter(fn (string $status): bool => $status === 'cancelled')->count(),
        ];
    }

    private function paymentAmountForMonth(Carbon $monthStart, Carbon $monthEnd, ?string $status = null): int
    {
        return (int) Payment::query()
            ->whereHas('reservation', fn (Builder $query) => $query
                ->whereDate('reservation_date', '>=', $monthStart->toDateString())
                ->whereDate('reservation_date', '<=', $monthEnd->toDateString()))
            ->when($status, fn (Builder $query, string $status) => $query->whereHas('reservation', fn (Builder $reservationQuery) => $reservationQuery->where('payment_status', $status)))
            ->sum('amount');
    }

    private function scopedUnpaidPaymentCount(Builder $reservationScope): int
    {
        $reservationIds = (clone $reservationScope)->select('id');

        return Payment::query()
            ->whereHas('reservation', fn (Builder $query) => $query->where('payment_status', 'unpaid'))
            ->whereIn('reservation_id', $reservationIds)
            ->count();
    }

    private function topRoomForMonth(Builder $reservationScope, Carbon $monthStart, Carbon $monthEnd): ?array
    {
        $row = (clone $reservationScope)
            ->selectRaw('room_id, COUNT(*) as borrow_count')
            ->whereDate('reservation_date', '>=', $monthStart->toDateString())
            ->whereDate('reservation_date', '<=', $monthEnd->toDateString())
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
            ->with(['room', 'user', 'timeSlot'])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservation_group_id ?: (string) $reservation->id)
            ->map(fn (Collection $group): array => $this->recentReservationGroupPayload($group))
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->all();
    }

    private function recentPayments(): array
    {
        return Payment::with(['reservation.room', 'reservation.user'])
            ->latest('created_at')
            ->limit(50)
            ->get()
            ->groupBy(fn (Payment $payment): string => $payment->reservation?->reservation_group_id ?: (string) $payment->reservation_id)
            ->map(fn (Collection $group): array => $this->recentPaymentGroupPayload($group))
            ->sortByDesc('created_at')
            ->take(5)
            ->values()
            ->all();
    }

    private function recentApprovals(): array
    {
        return Approval::with(['reservation.room', 'reservation.user', 'approver'])
            ->latest('decision_time')
            ->limit(50)
            ->get()
            ->groupBy(fn (Approval $approval): string => $approval->reservation?->reservation_group_id ?: (string) $approval->reservation_id)
            ->map(fn (Collection $group): array => $this->recentApprovalGroupPayload($group))
            ->sortByDesc('decision_time')
            ->take(5)
            ->values()
            ->all();
    }

    private function recentReservationGroupPayload(Collection $group): array
    {
        $sorted = $group->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->values();
        /** @var Reservation $first */
        $first = $sorted->first();
        /** @var Reservation $last */
        $last = $sorted->last();

        return [
            'id' => $first->id,
            'reservation_group_id' => $first->reservation_group_id,
            'slot_count' => $sorted->count(),
            'created_at' => $sorted->sortByDesc('created_at')->first()?->created_at?->toDateTimeString(),
            'start_time' => $first->start_time?->toDateTimeString(),
            'end_time' => $last->end_time?->toDateTimeString(),
            'status' => $this->groupStatus($sorted),
            'room_name' => $first->room?->name ?? '未知空間',
            'room_building' => $first->room?->building,
            'user_name' => $first->user?->name ?? $first->user?->email,
        ];
    }

    private function recentPaymentGroupPayload(Collection $group): array
    {
        $sorted = $group->sortBy(fn (Payment $payment): string => $payment->reservation?->start_time?->toDateTimeString() ?? '')->values();
        /** @var Payment $first */
        $first = $sorted->first();
        $reservationGroupId = $first->reservation?->reservation_group_id;
        $slotCount = $reservationGroupId
            ? Reservation::where('reservation_group_id', $reservationGroupId)->count()
            : 1;

        return [
            'id' => $first->id,
            'payment_ids' => $sorted->pluck('id')->values()->all(),
            'slot_count' => $slotCount,
            'amount' => $sorted->sum('amount'),
            'status' => $this->paymentStatusForPayments($sorted),
            'room_name' => $first->reservation?->room?->name ?? '未知空間',
            'user_name' => $first->reservation?->user?->name ?? $first->reservation?->user?->email,
            'created_at' => $sorted->sortByDesc('created_at')->first()?->created_at?->toDateTimeString(),
        ];
    }

    private function recentApprovalGroupPayload(Collection $group): array
    {
        /** @var Approval $first */
        $first = $group->sortByDesc('decision_time')->first();
        $reservationGroupId = $first->reservation?->reservation_group_id;
        $slotCount = $reservationGroupId
            ? Reservation::where('reservation_group_id', $reservationGroupId)->count()
            : 1;

        return [
            'id' => $first->id,
            'slot_count' => $slotCount,
            'decision' => $first->decision,
            'decision_time' => $first->decision_time?->toDateTimeString(),
            'room_name' => $first->reservation?->room?->name ?? '未知空間',
            'user_name' => $first->reservation?->user?->name ?? $first->reservation?->user?->email,
            'approver_name' => $first->approver?->name ?? $first->approver?->email,
        ];
    }

    private function roomRankings(Builder $reservationScope): array
    {
        return (clone $reservationScope)
            ->selectRaw('room_id, COUNT(*) as borrow_count')
            ->whereDate('reservation_date', '>=', now()->copy()->subDays(30)->toDateString())
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

        $payments = Payment::with(['reservation.room', 'reservation.timeSlot'])
            ->whereHas('reservation', fn (Builder $query) => $query->where('payment_status', 'unpaid'))
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

    private function paymentStatusForPayments(Collection $payments): string
    {
        if ($payments->contains(fn (Payment $payment): bool => $payment->reservation?->payment_status === 'unpaid')) {
            return 'unpaid';
        }

        if ($payments->contains(fn (Payment $payment): bool => $payment->reservation?->payment_status === 'cancelled')) {
            return 'cancelled';
        }

        return 'paid';
    }

    private function upcomingReservationsList(Builder $reservationScope, Carbon $now): array
    {
        return (clone $reservationScope)
            ->with('room')
            ->with('timeSlot')
            ->whereIn('reservation_status', ['pending', 'success'])
            ->whereDate('reservation_date', '>=', $now->toDateString())
            ->whereDate('reservation_date', '<=', $now->copy()->addDay()->toDateString())
            ->orderBy('reservation_date')
            ->orderBy('time_slot_id')
            ->get()
            ->filter(fn (Reservation $reservation): bool => $reservation->start_time >= $now && $reservation->start_time <= $now->copy()->addDay())
            ->groupBy(fn (Reservation $reservation): string => $reservation->reservation_group_id ?: (string) $reservation->id)
            ->map(fn (Collection $group): array => $this->upcomingReservationGroupPayload($group))
            ->sortBy('start_time')
            ->take(10)
            ->values()
            ->all();
    }

    private function upcomingReservationGroupPayload(Collection $group): array
    {
        $sorted = $group->sortBy(fn (Reservation $reservation): string => $reservation->start_time?->toDateTimeString() ?? '')->values();
        /** @var Reservation $first */
        $first = $sorted->first();
        /** @var Reservation $last */
        $last = $sorted->last();

        return [
            'id' => $first->id,
            'reservation_group_id' => $first->reservation_group_id,
            'date' => $first->start_time?->toDateString(),
            'start_time' => $first->start_time?->toDateTimeString(),
            'end_time' => $last->end_time?->toDateTimeString(),
            'reservation_status' => $this->groupStatus($sorted),
            'slot_count' => $sorted->count(),
            'room' => $first->room
                ? [
                    'id' => $first->room->id,
                    'name' => $first->room->name,
                    'type' => $first->room->type,
                    'building' => $first->room->building,
                ]
                : null,
            'slots' => $sorted
                ->map(fn (Reservation $reservation): array => [
                    'id' => $reservation->id,
                    'date' => $reservation->reservation_date?->format('Y-m-d') ?? $reservation->start_time?->format('Y-m-d'),
                    'time_slot_id' => $reservation->time_slot_id,
                    'start_time' => $reservation->start_time?->toDateTimeString(),
                    'end_time' => $reservation->end_time?->toDateTimeString(),
                    'reservation_status' => $reservation->reservation_status,
                    'room' => $reservation->room
                        ? [
                            'id' => $reservation->room->id,
                            'name' => $reservation->room->name,
                            'type' => $reservation->room->type,
                            'building' => $reservation->room->building,
                        ]
                        : null,
                ])
                ->all(),
        ];
    }

    private function groupStatus(Collection $reservations): string
    {
        $statuses = $reservations->pluck('reservation_status');

        foreach (['pending', 'success', 'cancelled', 'rejected'] as $status) {
            if ($statuses->contains($status)) {
                return $status;
            }
        }

        return (string) ($statuses->first() ?? '-');
    }

    private function hasAnyRole(array $roles, array $allowedRoles): bool
    {
        return collect($allowedRoles)->contains(fn (string $role): bool => in_array($role, $roles, true));
    }
}
