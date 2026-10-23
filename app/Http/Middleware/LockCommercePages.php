<?php

namespace App\Http\Middleware;

use App\Support\Filament\CommerceNavigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class LockCommercePages
{
    public function handle(Request $request, Closure $next): Response
    {
        if (CommerceNavigation::locked() && $request->is(
            'commerce-preview', 'cart', 'cart/*', 'checkout', 'orders', 'orders/*',
            'admin/products', 'admin/products/*', 'admin/product-categories', 'admin/product-categories/*',
            'admin/orders', 'admin/orders/*', 'admin/payments', 'admin/payments/*', 'admin/commerce-settings',
            'admin/package-checkouts', 'admin/package-checkouts/*',
        )) {
            return response()->view('commerce.preview', ['portalUrl' => $request->is('admin/*') ? '/admin' : '/'], 503)
                ->header('Cache-Control', 'no-store, private');
        }

        return $next($request);
    }
}
