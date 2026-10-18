<?php

namespace App\Http\Responses;

use App\Services\Carts\CartSession;
use Filament\Auth\Http\Responses\LoginResponse;
use Filament\Facades\Filament;
use Illuminate\Http\RedirectResponse;
use Livewire\Features\SupportRedirects\Redirector;

final class CustomerLoginResponse extends LoginResponse
{
    public function toResponse($request): RedirectResponse|Redirector
    {
        if (Filament::getCurrentPanel()?->getId() === 'account' && $request->hasSession() && app(CartSession::class)->hasContext($request->session())) {
            return redirect()->route('cart.show');
        }

        return parent::toResponse($request);
    }
}
