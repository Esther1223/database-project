<?php

use App\Http\Controllers\Admin\AffiliationController as AdminAffiliationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomSectionController;
use App\Http\Controllers\TimeSlotController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route('login');
});

Route::get('/dashboard', [DashboardController::class, 'index'])->middleware(['auth'])->name('dashboard');
Route::get('/dashboard/summary', [DashboardController::class, 'summary'])->middleware(['auth'])->name('dashboard.summary');

Route::middleware('auth')->group(function () {
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::get('/rooms/{room}', [RoomController::class, 'show'])->name('rooms.show');
});

Route::middleware(['auth', 'role:管理員'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/affiliations', [AdminAffiliationController::class, 'index'])->name('affiliations.index');
    Route::post('/affiliations', [AdminAffiliationController::class, 'store'])->name('affiliations.store');
    Route::put('/affiliations/{affiliation}', [AdminAffiliationController::class, 'update'])->name('affiliations.update');
    Route::delete('/affiliations/{affiliation}', [AdminAffiliationController::class, 'destroy'])->name('affiliations.destroy');

    Route::redirect('/roles', '/admin/users')->name('roles.index');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles');
});

Route::middleware(['auth', 'role:管理員'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');
});

Route::middleware(['auth', 'role:行政人員'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/list', [PaymentController::class, 'list'])->name('payments.list');
    Route::patch('/payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.status');

    Route::get('/reports/reservations', [ReportController::class, 'reservationReport'])->name('reports.reservations');
    Route::get('/reports/reservations/monthly', [ReportController::class, 'monthlyReservations'])->name('reports.reservations.monthly');
    Route::get('/reports/rooms/usage', [ReportController::class, 'roomUsage'])->name('reports.rooms.usage');
    Route::get('/reports/payments', [ReportController::class, 'payments'])->name('reports.payments');

    Route::get('/reservations/history', [ReservationController::class, 'history'])->name('reservations.history');
});

Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth');
});

Route::middleware('auth')->group(function () {
    Route::get('/rooms/{room}/available-sections', [RoomSectionController::class, 'getAvailable']);
    Route::get('/rooms/{room}/disabled-sections', [RoomSectionController::class, 'getDisabled']);
});

Route::middleware(['auth', 'role:管理員'])->group(function () {
    Route::get('/admin/rooms/{room}/time-slots', [TimeSlotController::class, 'index'])->name('admin.rooms.time-slots.index');
    Route::post('/admin/rooms/{room}/time-slots', [TimeSlotController::class, 'store'])->name('admin.rooms.time-slots.store');
    Route::put('/admin/rooms/{room}/time-slots/{timeSlot}', [TimeSlotController::class, 'update'])->name('admin.rooms.time-slots.update');
    Route::delete('/admin/rooms/{room}/time-slots/{timeSlot}', [TimeSlotController::class, 'destroy'])->name('admin.rooms.time-slots.destroy');
    Route::patch('/time-slots/{timeSlot}/status', [TimeSlotController::class, 'updateStatus']);
});

Route::middleware('auth')->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/create', [ReservationController::class, 'create']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/my', [ReservationController::class, 'myReservations']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::patch('/reservations/{reservation}/cancel-single', [ReservationController::class, 'cancelSingle']);
});

Route::middleware(['auth', 'role:行政人員'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::get('/pending', [ApprovalController::class, 'pending'])->name('pending');
    Route::get('/history', [ApprovalController::class, 'history'])->name('history');
    Route::patch('/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::patch('/reject', [ApprovalController::class, 'reject'])->name('reject');
});

require __DIR__.'/auth.php';
