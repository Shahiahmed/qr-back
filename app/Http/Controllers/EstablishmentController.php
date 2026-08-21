<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstablishmentRequest;
use App\Http\Requests\StoreVenueImageRequest;
use App\Http\Requests\UpdateEstablishmentRequest;
use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
use App\Support\MenuStarter;
use App\Support\VenueImage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class EstablishmentController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        // Scoped through the owner: a venue belonging to anyone else must not
        // appear here, and must not be reachable by guessing an id.
        $establishments = $request->user()
            ->establishments()
            // Each venue's own live grant drives its access window and content
            // caps — load the grant and its plan once, no N+1 across the list.
            ->with('currentSubscription.plan')
            ->latest('id')
            ->get();

        return EstablishmentResource::collection($establishments);
    }

    public function store(StoreEstablishmentRequest $request): JsonResponse
    {
        $establishment = $request->user()
            ->establishments()
            ->create($request->validated());

        // Every new venue opens with a bilingual skeleton the owner can edit.
        MenuStarter::apply($establishment);

        return EstablishmentResource::make($establishment)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Establishment $establishment): EstablishmentResource
    {
        $this->authorizeOwner($request, $establishment);

        return EstablishmentResource::make($establishment);
    }

    public function update(
        UpdateEstablishmentRequest $request,
        Establishment $establishment,
    ): EstablishmentResource {
        $this->authorizeOwner($request, $establishment);

        $establishment->update($request->validated());

        return EstablishmentResource::make($establishment);
    }

    public function destroy(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeOwner($request, $establishment);

        $establishment->delete();

        return response()->json(status: 204);
    }

    /**
     * Upload (or replace) the venue's cover or logo. Multipart, not JSON —
     * the processed image is downscaled to WebP before it lands on disk.
     */
    public function storeImage(
        StoreVenueImageRequest $request,
        Establishment $establishment,
    ): EstablishmentResource {
        $this->authorizeOwner($request, $establishment);

        VenueImage::store($establishment, $request->validated('kind'), $request->file('file'));

        return EstablishmentResource::make($establishment->fresh());
    }

    /** Remove one image slot (cover or logo) and its file. */
    public function destroyImage(
        Request $request,
        Establishment $establishment,
        string $kind,
    ): EstablishmentResource {
        $this->authorizeOwner($request, $establishment);
        abort_unless(array_key_exists($kind, VenueImage::KINDS), 404);

        VenueImage::remove($establishment, $kind);

        return EstablishmentResource::make($establishment->fresh());
    }

    /**
     * Start binding a Telegram chat to this venue: mint a one-time token and
     * return the bot deep link. The owner (or their staff) opens it and presses
     * Start; the webhook then stores the chat id (see TelegramWebhookController).
     * Returns 409 when the bot isn't configured on the server.
     */
    public function linkTelegram(Request $request, Establishment $establishment): JsonResponse
    {
        $this->authorizeOwner($request, $establishment);

        $username = config('services.telegram.bot_username');
        abort_if(! $username, 409, 'Telegram bot is not configured.');

        $token = $establishment->issueTelegramLinkToken();

        return response()->json([
            'url' => "https://t.me/{$username}?start={$token}",
        ]);
    }

    /** Unbind the Telegram chat — waiter calls stop until reconnected. */
    public function unlinkTelegram(Request $request, Establishment $establishment): EstablishmentResource
    {
        $this->authorizeOwner($request, $establishment);

        // Both columns are out of #[Fillable]; clear them in trusted code only.
        $establishment->forceFill([
            'telegram_chat_id' => null,
            'telegram_link_token' => null,
        ])->save();

        return EstablishmentResource::make($establishment->fresh());
    }

    /**
     * 404 rather than 403: confirming that an id exists but belongs to someone
     * else leaks how many venues the service holds.
     */
    private function authorizeOwner(Request $request, Establishment $establishment): void
    {
        abort_unless($establishment->user_id === $request->user()->id, 404);
    }
}
