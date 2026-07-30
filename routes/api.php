<?php

use App\Http\Controllers\Auth\CurrentUserController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\UpdatePasswordController;
use App\Http\Controllers\ApplyMenuStarterController;
use App\Http\Controllers\DishController;
use App\Http\Controllers\EstablishmentController;
use App\Http\Controllers\MenuCategoryController;
use App\Http\Controllers\PublicMenuController;
use App\Http\Controllers\PublicPlansController;
use App\Http\Controllers\PublicSeoController;
use App\Http\Controllers\SubscriptionRequestController;
use Illuminate\Support\Facades\Route;

/*
 * The guest menu — what the QR code on the table points at. No auth, no
 * session; cached whole and dropped when an owner edits anything.
 */
Route::get('/public/menu/{slug}', PublicMenuController::class)->name('public.menu');

/*
 * Subscription plans shown on the landing. No auth; cached and dropped on edit.
 */
Route::get('/plans', PublicPlansController::class)->name('public.plans');

/*
 * Site-wide SEO settings for the landing's metadata. No auth; cached and
 * dropped when the admin saves the settings page in /admin.
 */
Route::get('/seo', PublicSeoController::class)->name('public.seo');

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

    Route::get('/user', CurrentUserController::class)->name('user');
    Route::put('/user/password', UpdatePasswordController::class)->name('user.password');

    /*
     * Subscription: the owner's current tier and their applications. Approval
     * happens in the admin panel, not here — this side only files requests.
     */
    Route::get('/subscription', [SubscriptionRequestController::class, 'current'])->name('subscription.current');
    Route::get('/subscription-requests', [SubscriptionRequestController::class, 'index'])->name('subscription-requests.index');
    Route::post('/subscription-requests', [SubscriptionRequestController::class, 'store'])->name('subscription-requests.store');

    // Venues. Every route is scoped to the signed-in owner.
    Route::apiResource('establishments', EstablishmentController::class);

    // Cover / logo upload. Multipart; images are downscaled to WebP server-side.
    Route::post('establishments/{establishment}/image', [EstablishmentController::class, 'storeImage']);
    Route::delete('establishments/{establishment}/image/{kind}', [EstablishmentController::class, 'destroyImage']);

    /*
     * Menu, nested under its venue so the tenant is always in the URL and
     * cannot be forgotten when scoping.
     */
    Route::prefix('establishments/{establishment}')->group(function () {
        Route::get('menu', [MenuCategoryController::class, 'index'])->name('menu.index');
        Route::post('menu/starter', ApplyMenuStarterController::class)->name('menu.starter');

        Route::post('categories', [MenuCategoryController::class, 'store']);
        // Before the {category} route so "reorder" isn't taken for an id.
        Route::patch('categories/reorder', [MenuCategoryController::class, 'reorder']);
        Route::patch('categories/{category}', [MenuCategoryController::class, 'update']);
        Route::delete('categories/{category}', [MenuCategoryController::class, 'destroy']);

        Route::post('dishes', [DishController::class, 'store']);
        Route::patch('dishes/{dish}', [DishController::class, 'update']);
        Route::delete('dishes/{dish}', [DishController::class, 'destroy']);
        // Dish photo. Multipart; centre-cropped to a square WebP server-side.
        Route::post('dishes/{dish}/image', [DishController::class, 'storeImage']);
        Route::delete('dishes/{dish}/image', [DishController::class, 'destroyImage']);
    });
});
