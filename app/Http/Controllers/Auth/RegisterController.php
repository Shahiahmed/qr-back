<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
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
        $user = User::create($request->validated());

        event(new Registered($user));

        Auth::login($user);

        // A fresh session id, so a token captured before signup is useless.
        $request->session()->regenerate();

        return UserResource::make($user)
            ->response()
            ->setStatusCode(201);
    }
}
