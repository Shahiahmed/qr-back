<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * Auth for the admin panel. Sanctum runs in SPA mode, so these rely on the
 * session cookie: the front end must call `/sanctum/csrf-cookie` once before
 * the first write, and send every request with credentials.
 */
Route::post('/register', RegisterController::class)
    ->middleware('throttle:10,1')
    ->name('register');

Route::post('/login', [LoginController::class, 'store'])
    ->middleware('throttle:10,1')
    ->name('login');

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    // Who am I — the front end calls this to restore state on a reload.
    Route::get('/user', fn (Request $request) => UserResource::make($request->user()))
        ->name('user');
});
