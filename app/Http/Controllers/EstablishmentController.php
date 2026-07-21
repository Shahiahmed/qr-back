<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreEstablishmentRequest;
use App\Http\Requests\UpdateEstablishmentRequest;
use App\Http\Resources\EstablishmentResource;
use App\Models\Establishment;
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
            ->latest('id')
            ->get();

        return EstablishmentResource::collection($establishments);
    }

    public function store(StoreEstablishmentRequest $request): JsonResponse
    {
        $establishment = $request->user()
            ->establishments()
            ->create($request->validated());

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
     * 404 rather than 403: confirming that an id exists but belongs to someone
     * else leaks how many venues the service holds.
     */
    private function authorizeOwner(Request $request, Establishment $establishment): void
    {
        abort_unless($establishment->user_id === $request->user()->id, 404);
    }
}
