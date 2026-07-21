<?php

namespace App\Http\Controllers;

use App\Models\Establishment;
use Illuminate\Database\Eloquent\Model;

/**
 * Tenant scoping for the panel.
 *
 * Everything answers 404 rather than 403: a 403 confirms the row exists,
 * which is enough to count another venue's menu by walking ids.
 */
trait ScopesToOwner
{
    protected function authorizeOwner(Establishment $establishment): void
    {
        abort_unless($establishment->user_id === request()->user()->id, 404);
    }

    /** Guards against mixing a valid id from a different tenant. */
    protected function authorizeBelongsTo(Model $model, Establishment $establishment): void
    {
        abort_unless($model->establishment_id === $establishment->id, 404);
    }
}
