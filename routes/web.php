<?php

use App\Http\Controllers\Auth\SessionAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get('/login', [SessionAuthController::class, 'create'])->name('login');
Route::post('/login', [SessionAuthController::class, 'store'])->name('login.store');
Route::post('/logout', [SessionAuthController::class, 'destroy'])->middleware('auth')->name('logout');
Route::view('/dashboard', 'dashboard')->middleware('auth')->name('dashboard');
