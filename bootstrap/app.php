<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        /*
         * Sanctum in SPA mode: the front end authenticates with the session
         * cookie rather than a bearer token, so API requests coming from a
         * stateful domain need the session and CSRF middleware.
         */
        $middleware->statefulApi();

        // Public POSTs that can't carry a session CSRF token: the Telegram
        // webhook is a server-to-server call, and the guest "call waiter" comes
        // from the statically-served menu. The webhook is guarded by a path
        // secret instead; waiter-call is throttled and reason-enumerated.
        $middleware->validateCsrfTokens(except: [
            'api/telegram/webhook/*',
            'api/public/menu/*/waiter-call',
            'api/public/menu/*/order',
        ]);

        // Validation and auth messages go straight into the UI, so they have
        // to come back in the language the visitor is reading.
        $middleware->api(prepend: [\App\Http\Middleware\SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*'),
        );
    })->create();
