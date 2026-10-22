<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePhoneRequest;
use App\Http\Resources\UserResource;

/**
 * Owner sets or changes their WhatsApp number from the profile page — the
 * only way for Google sign-ins and pre-phone accounts to add one.
 */
class UpdatePhoneController extends Controller
{
    public function __invoke(UpdatePhoneRequest $request): UserResource
    {
        $user = $request->user();
        $user->update(['phone' => $request->validated('phone')]);

        return UserResource::make($user);
    }
}
