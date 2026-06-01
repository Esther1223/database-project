<?php

use App\Http\Controllers\Admin\AfflicationController as AdminAfflicationController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\ApprovalController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\PaymentController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\RoomController;
use App\Http\Controllers\RoomSectionController;
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
    Route::get('/afflications', [AdminAfflicationController::class, 'index'])->name('afflications.index');
    Route::post('/afflications', [AdminAfflicationController::class, 'store'])->name('afflications.store');
    Route::put('/afflications/{afflication}', [AdminAfflicationController::class, 'update'])->name('afflications.update');
    Route::delete('/afflications/{afflication}', [AdminAfflicationController::class, 'destroy'])->name('afflications.destroy');

    Route::get('/rooms', [RoomController::class, 'index'])->name('rooms.index');
    Route::post('/rooms', [RoomController::class, 'store'])->name('rooms.store');
    Route::put('/rooms/{room}', [RoomController::class, 'update'])->name('rooms.update');
    Route::delete('/rooms/{room}', [RoomController::class, 'destroy'])->name('rooms.destroy');

    Route::get('/users', [AdminUserController::class, 'index'])->name('users.index');
    Route::post('/users', [AdminUserController::class, 'store'])->name('users.store');
    Route::put('/users/{user}', [AdminUserController::class, 'update'])->name('users.update');
    Route::delete('/users/{user}', [AdminUserController::class, 'destroy'])->name('users.destroy');
    Route::patch('/users/{user}/status', [AdminUserController::class, 'updateStatus'])->name('users.status');
    Route::put('/users/{user}/roles', [AdminUserController::class, 'updateRoles'])->name('users.roles');
});

Route::middleware(['auth', 'role:管理員,行政人員'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/payments', [PaymentController::class, 'index'])->name('payments.index');
    Route::get('/payments/list', [PaymentController::class, 'list'])->name('payments.list');
    Route::patch('/payments/{payment}/status', [PaymentController::class, 'updateStatus'])->name('payments.status');

    Route::get('/reports/reservations', [ReportController::class, 'reservationReport'])->name('reports.reservations');
    Route::get('/reports/reservations/monthly', [ReportController::class, 'monthlyReservations'])->name('reports.reservations.monthly');

    Route::get('/reservations/history', [ReservationController::class, 'history'])->name('reservations.history');
});

Route::prefix('api')->group(function () {
    Route::post('/login', [AuthController::class, 'login']);
    Route::post('/logout', [AuthController::class, 'logout'])->middleware('auth');
    Route::get('/user', [AuthController::class, 'user'])->middleware('auth');
});

Route::get('/rooms/{room}/available-sections', [RoomSectionController::class, 'getAvailable']);
Route::get('/rooms/{room}/disabled-sections', [RoomSectionController::class, 'getDisabled']);

Route::patch('/room-sections/{roomSection}/status', [RoomSectionController::class, 'updateAvailability']);
Route::patch('/time-slots/{timeSlot}/status', [RoomSectionController::class, 'updateAdminDisable']);

Route::middleware('auth')->group(function () {
    Route::get('/reservations', [ReservationController::class, 'index']);
    Route::get('/reservations/create', [ReservationController::class, 'create']);
    Route::post('/reservations', [ReservationController::class, 'store']);
    Route::get('/reservations/my', [ReservationController::class, 'myReservations']);
    Route::get('/reservations/{reservation}', [ReservationController::class, 'show']);
    Route::patch('/reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
});

Route::middleware(['auth', 'role:管理員,行政人員'])->prefix('approvals')->name('approvals.')->group(function () {
    Route::get('/', [ApprovalController::class, 'index'])->name('index');
    Route::get('/pending', [ApprovalController::class, 'pending'])->name('pending');
    Route::get('/history', [ApprovalController::class, 'history'])->name('history');
    Route::patch('/approve', [ApprovalController::class, 'approve'])->name('approve');
    Route::patch('/reject', [ApprovalController::class, 'reject'])->name('reject');
});

require __DIR__.'/auth.php';
