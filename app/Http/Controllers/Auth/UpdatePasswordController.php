<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdatePasswordRequest;
use Illuminate\Http\Response;

/**
 * Owner changes their own password from the profile page.
 */
class UpdatePasswordController extends Controller
{
    public function __invoke(UpdatePasswordRequest $request): Response
    {
        $request->user()->update([
            'password' => $request->validated('password'),
        ]);

        // Fresh session after a credential change.
        $request->session()->regenerate();

        return response()->noContent();
    }
}
