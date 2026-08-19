<?php

use App\Http\Controllers\Auth\GoogleAuthController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ('Hello world');
});

/*
 * "Sign in with Google". These live here, not in api.php, because OAuth is a
 * full-page redirect flow that must start a session (web middleware group) to
 * log the user into the same `.qr-menu.kz` cookie the SPA already uses. The
 * callback bounces back to the front-end dashboard, already authenticated.
 */
Route::get('/auth/google/redirect', [GoogleAuthController::class, 'redirect'])
    ->name('auth.google.redirect');
Route::get('/auth/google/callback', [GoogleAuthController::class, 'callback'])
    ->name('auth.google.callback');
