<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse as SymfonyRedirect;
use Throwable;

/**
 * "Sign in with Google" for restaurant owners, on top of the existing Sanctum
 * SPA session. The flow is full-page redirects (OAuth cannot run over XHR), so
 * these routes live on `web.php` where a session is started: the callback logs
 * the user into the same `.qr-menu.kz` cookie the password flow uses, then
 * bounces back to the SPA dashboard already authenticated.
 */
class GoogleAuthController extends Controller
{
    /** Locales the SPA serves; anything else falls back to the default. */
    private const LOCALES = ['ru', 'kz'];

    private const DEFAULT_LOCALE = 'ru';

    /** Send the visitor to Google's consent screen. */
    public function redirect(Request $request): SymfonyRedirect
    {
        // Remember which language to return them to. Carried in the session
        // (shared with the callback), not the OAuth state Socialite owns.
        $locale = $request->query('locale');
        $request->session()->put(
            'oauth_locale',
            in_array($locale, self::LOCALES, true) ? $locale : self::DEFAULT_LOCALE,
        );

        return Socialite::driver('google')->redirect();
    }

    /** Handle Google's redirect back: find or create the user, then sign in. */
    public function callback(Request $request): RedirectResponse
    {
        $locale = $request->session()->pull('oauth_locale', self::DEFAULT_LOCALE);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            // User denied consent, or the exchange failed. Back to login.
            return redirect($this->frontend($locale, '/login?error=google'));
        }

        $email = mb_strtolower((string) $googleUser->getEmail());

        if ($email === '') {
            return redirect($this->frontend($locale, '/login?error=google'));
        }

        // Match on the stable Google subject first, then fall back to email so
        // an existing password account is linked rather than duplicated.
        $user = User::query()->where('google_id', $googleUser->getId())->first()
            ?? User::query()->where('email', $email)->first();

        if ($user === null) {
            $user = new User();
            $user->email = $email;
            // A Google-only account has no password; the column is nullable and
            // password login simply fails the hash check for it.
            $user->password = null;
        }

        // google_id / avatar are not mass-assignable (same discipline as
        // is_admin) — set them here, in this trusted callback, via forceFill.
        $user->forceFill([
            'google_id' => $googleUser->getId(),
            'avatar' => $googleUser->getAvatar(),
            // Google has already verified the address.
            'email_verified_at' => $user->email_verified_at ?? now(),
        ]);

        if (blank($user->name)) {
            $user->name = $googleUser->getName() ?: mb_strstr($email, '@', true);
        }

        $user->save();

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return redirect($this->frontend($locale, '/dashboard'));
    }

    /** Absolute SPA URL for a locale-prefixed path. */
    private function frontend(string $locale, string $path): string
    {
        $base = rtrim((string) config('app.frontend_url'), '/');

        return "{$base}/{$locale}{$path}";
    }
}
