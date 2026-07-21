<?php

namespace App\Http\Controllers;

use App\Support\PublicMenu;
use Illuminate\Http\JsonResponse;

class PublicMenuController extends Controller
{
    /** No auth: this is what the QR code on the table points at. */
    public function __invoke(string $slug): JsonResponse
    {
        $menu = PublicMenu::forSlug($slug);

        abort_if($menu === null, 404);

        return response()->json(['data' => $menu]);
    }
}
