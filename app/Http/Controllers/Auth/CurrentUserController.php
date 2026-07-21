<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;

/**
 * Who am I — the front end calls this to restore state on a reload.
 *
 * A controller rather than a closure so the route list stays cacheable:
 * `route:cache` refuses a file containing closures, and on a shared server
 * that failure surfaces as a broken deploy rather than a clear message.
 */
class CurrentUserController extends Controller
{
    public function __invoke(Request $request): UserResource
    {
        return UserResource::make($request->user());
    }
}
