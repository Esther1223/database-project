<?php

use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::middleware('guest')->group(function () {
    Route::get('login', function () {
        return Inertia::render('Auth/LoginPage');
    })->name('login');
});