<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)
    ->use(RefreshDatabase::class)
    ->beforeEach(function () {
        /*
         * Sanctum only starts a session for requests whose Origin is one of
         * the stateful domains — without the header it treats the call as a
         * token API and `$request->session()` blows up. Tests have to look
         * like the SPA they are standing in for.
         */
        $this->withHeader('Origin', env('FRONTEND_URL', 'http://localhost:3000'));
    })
    ->in('Feature');
