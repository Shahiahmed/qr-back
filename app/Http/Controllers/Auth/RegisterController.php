<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ReferralService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class RegisterController extends Controller
{
    /**
     * Create the account and log it straight in.
     *
     * The user is signed in here rather than being bounced to a login form:
     * the next step of onboarding is naming the venue, and asking for the
     * password again in between only loses people.
     */
    public function __invoke(RegisterRequest $request): JsonResponse
    {
        // referral_code in the payload is the INVITER's code, not a fillable
        // column — create() only takes name/email/password (own code is minted
        // in the model's creating hook).
        $user = User::create($request->safe()->only(['name', 'email', 'password']));

        // Bind the inviter (if the code resolves to a real, different owner) and
        // grant this new owner their welcome discount. Failures here must not
        // sink the signup, so a miss is a silent no-op inside the service.
        if ($code = $request->validated('referral_code')) {
            $referrer = User::where('referral_code', $code)->first();
            if ($referrer && $referrer->id !== $user->id) {
                ReferralService::attachReferrer($user, $referrer);
            }
        }

        event(new Registered($user));

        Auth::login($user);

        // A fresh session id, so a token captured before signup is useless.
        $request->session()->regenerate();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }
}
