<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
 * The guest menu — what the QR code on the table points at. No auth, no
 * session; cached whole and dropped when an owner edits anything.
 */
Route::get('/public/menu/{slug}', PublicMenuController::class)->name('public.menu');

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

    // Venues. Every route is scoped to the signed-in owner.
    Route::apiResource('establishments', EstablishmentController::class);

    /*
     * Menu, nested under its venue so the tenant is always in the URL and
     * cannot be forgotten when scoping.
     */
    Route::prefix('establishments/{establishment}')->group(function () {
        Route::get('menu', [MenuCategoryController::class, 'index'])->name('menu.index');

        Route::post('categories', [MenuCategoryController::class, 'store']);
        Route::patch('categories/{category}', [MenuCategoryController::class, 'update']);
        Route::delete('categories/{category}', [MenuCategoryController::class, 'destroy']);

        Route::post('dishes', [DishController::class, 'store']);
        Route::patch('dishes/{dish}', [DishController::class, 'update']);
        Route::delete('dishes/{dish}', [DishController::class, 'destroy']);
    });
});
