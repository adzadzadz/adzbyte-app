<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Carts\StorefrontCredential;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class EnsureStorefrontToken
{
    public function handle(Request $request, Closure $next, string $ability): Response
    {
        $user = $request->user();
        $token = $user?->currentAccessToken();
        abort_unless($user instanceof User && StorefrontCredential::allowed($user, $token)
            && in_array($ability, StorefrontCredential::abilities(), true) && $token->can($ability), 403);

        return $next($request);
    }
}
