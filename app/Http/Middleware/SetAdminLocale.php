<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Forces Russian for the admin panel only.
 *
 * The API picks locale per request from Accept-Language (SetLocale); the panel
 * has no such header, and its operators are Russian-speaking, so we pin `ru`
 * here — Filament ships the matching translations for its own chrome.
 */
class SetAdminLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        app()->setLocale('ru');

        return $next($request);
    }
}
