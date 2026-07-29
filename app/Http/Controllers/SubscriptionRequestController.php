<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreSubscriptionRequestRequest;
use App\Http\Resources\SubscriptionRequestResource;
use App\Http\Resources\SubscriptionResource;
use App\Models\SubscriptionRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class SubscriptionRequestController extends Controller
{
    /** The owner's current subscription plus any pending application. */
    public function current(Request $request): JsonResponse
    {
        $user = $request->user();
        $active = $user->activeSubscription();

        $pending = $user->subscriptionRequests()
            ->with('plan')
            ->where('status', SubscriptionRequest::STATUS_NEW)
            ->latest('id')
            ->first();

        return response()->json([
            'subscription' => $active
                ? SubscriptionResource::make($active->load('plan'))
                : null,
            'pending_request' => $pending
                ? SubscriptionRequestResource::make($pending)
                : null,
        ]);
    }

    /** The owner's own application history. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $requests = $request->user()
            ->subscriptionRequests()
            ->with('plan')
            ->latest('id')
            ->get();

        return SubscriptionRequestResource::collection($requests);
    }

    public function store(StoreSubscriptionRequestRequest $request): JsonResponse
    {
        $user = $request->user();

        // One open application at a time: a second would just confuse the queue.
        $existing = $user->subscriptionRequests()
            ->where('status', SubscriptionRequest::STATUS_NEW)
            ->first();

        if ($existing) {
            return response()->json([
                'message' => __('subscription.already_pending'),
            ], 409);
        }

        $subscriptionRequest = $user->subscriptionRequests()->create($request->validated());

        return SubscriptionRequestResource::make($subscriptionRequest->load('plan'))
            ->response()
            ->setStatusCode(201);
    }
}
