<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequestRequest;
use App\Http\Resources\SubscriptionRequestResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\Establishment;
use App\Models\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionRequestController extends Controller
{
    /**
     * Per-menu subscription status: each of the owner's venues with its access
     * window, its live grant (if any) and any request still awaiting a decision.
     * Subscriptions are billed per menu, so there is no single account-wide plan.
     */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();

        $establishments = $user->establishments()
            ->with('currentSubscription.plan')
            ->latest('id')
            ->get();

        // One lookup for all pending requests, indexed by their menu.
        $pending = $user->subscriptionRequests()
            ->with('plan')
            ->where('status', SubscriptionRequest::STATUS_NEW)
            ->latest('id')
            ->get()
            ->keyBy('establishment_id');

        $menus = $establishments->map(function (Establishment $establishment) use ($pending) {
            $grant = $establishment->currentSubscription;
            $request = $pending->get($establishment->id);

            return [
                'id' => $establishment->id,
                'name' => $establishment->name,
                'slug' => $establishment->slug,
                'access_source' => $establishment->accessSource(),
                'access_ends_at' => $establishment->accessEndsAt()?->toIso8601String(),
                'days_left' => $establishment->daysLeft(),
                'is_expired' => $establishment->isExpired(),
                'subscription' => $grant && $grant->isActive()
                    ? SubscriptionResource::make($grant)
                    : null,
                'pending_request' => $request
                    ? SubscriptionRequestResource::make($request)
                    : null,
            ];
        });

        return response()->json(['menus' => $menus]);
    }

    /** The owner's own application history. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = $request->user()
            ->subscriptionRequests()
            ->with(['plan', 'establishment'])
            ->latest('id')
            ->get();

        return SubscriptionRequestResource::collection($requests);
    }

    public function store(StoreSubscriptionRequestRequest $request): JsonResponse
    {
        $user = $request->user();

        // One open application per MENU: a second for the same venue would just
        // confuse the queue. A different venue may still have its own request.
        $existing = $user->subscriptionRequests()
            ->where('establishment_id', $request->validated('establishment_id'))
            ->where('status', SubscriptionRequest::STATUS_NEW)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('subscription.already_pending'),
            ], 409);
        }

        $subscriptionRequest = $user->subscriptionRequests()->create($request->validated());

        return SubscriptionRequestResource::make($subscriptionRequest->load(['plan', 'establishment']))
            ->response()
            ->setStatusCode(201);
    }
}
