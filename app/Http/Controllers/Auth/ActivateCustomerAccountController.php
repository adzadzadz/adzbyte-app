<?php

namespace App\Http\Controllers\Auth;

use App\Actions\Customers\ActivateCustomerAccount;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateCustomerAccountRequest;
use App\Models\User;
use App\Services\Carts\CartSession;
use App\Support\Records\RecordVisibility;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ActivateCustomerAccountController extends Controller
{
    public function edit(User $user, string $hash): View|RedirectResponse
    {
        RecordVisibility::assertVisible($user);
        abort_unless(
            $user->hasRole(UserRole::Customer->value)
            && hash_equals(sha1($user->getEmailForVerification()), $hash),
            403,
        );

        if ($user->hasVerifiedEmail()) {
            return redirect()->route('filament.account.auth.login');
        }

        return view('auth.activate-account', ['user' => $user]);
    }

    public function update(
        ActivateCustomerAccountRequest $request,
        User $user,
        ActivateCustomerAccount $activateCustomerAccount,
    ): RedirectResponse {
        if (! $activateCustomerAccount->handle($user, $request->validated('password'), (string) $request->route('hash'))) {
            return redirect()->route('filament.account.auth.login');
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect(app(CartSession::class)->hasContext($request->session()) ? '/cart' : '/')->with('status', 'Your account is active.');
    }
}
