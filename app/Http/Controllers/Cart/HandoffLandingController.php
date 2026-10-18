<?php

namespace App\Http\Controllers\Cart;

use App\Services\Carts\CommerceOrigins;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class HandoffLandingController
{
    public function show(Request $request): Response
    {
        abort_unless($request->query() === [], 404);
        $nonce = base64_encode(random_bytes(18));

        return response()->view('cart.handoff', ['nonce' => $nonce, 'shop' => CommerceOrigins::storefront().'/shop'])
            ->header('Cache-Control', 'no-store, private')
            ->header('Referrer-Policy', 'no-referrer')
            ->header('X-Frame-Options', 'DENY')
            ->header('Content-Security-Policy', "default-src 'none'; script-src 'nonce-{$nonce}'; style-src 'nonce-{$nonce}'; connect-src 'self'; base-uri 'none'; form-action 'self'; frame-ancestors 'none'");
    }
}
