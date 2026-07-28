<?php

use App\Models\User;

/*
 * The panel is Filament (session/web guard), separate from the Sanctum SPA.
 * Only accounts flagged is_admin may enter — everyone else is a restaurant
 * owner who belongs in the cabinet, not here.
 */

it('redirects guests to the panel login', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});

it('forbids authenticated non-admins', function () {
    $owner = User::factory()->create(['is_admin' => false]);

    $this->actingAs($owner)
        ->get('/admin')
        ->assertForbidden();
});

it('lets admins into the dashboard', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)
        ->get('/admin')
        ->assertOk();
});

it('keeps is_admin off the mass-assignable attributes', function () {
    // The public register/update paths must never let a user grant the flag.
    $user = User::factory()->create(['is_admin' => false]);

    $user->fill(['is_admin' => true]);

    expect($user->is_admin)->toBeFalse();
});
