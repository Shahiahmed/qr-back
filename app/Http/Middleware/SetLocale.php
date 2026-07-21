<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Answers in the language the caller is reading.
 *
 * Validation and auth messages are shown verbatim next to the form fields, so
 * a Kazakh visitor must not get «The email field is required.»
 */
class SetLocale
{
    /** Route prefix `kz` maps to the `kk` language files — `kz` is a country. */
    private const SUPPORTED = ['ru' => 'ru', 'kk' => 'kk', 'kz' => 'kk'];

    public function handle(Request $request, Closure $next): Response
    {
        $requested = $request->header('Accept-Language', '');

        foreach (explode(',', $requested) as $part) {
            $tag = mb_strtolower(trim(explode(';', $part)[0]));
            $primary = explode('-', $tag)[0];

            if (isset(self::SUPPORTED[$primary])) {
                App::setLocale(self::SUPPORTED[$primary]);
                break;
            }
        }

        return $next($request);
    }
}
