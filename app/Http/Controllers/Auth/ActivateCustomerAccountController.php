<?php

namespace App\Http\Controllers\Auth;

use App\Enums\UserRole;
use App\Events\CustomerAccountActivated;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ActivateCustomerAccountRequest;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ActivateCustomerAccountController extends Controller
{
    public function edit(User $user, string $hash): View|RedirectResponse
    {
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

    public function update(ActivateCustomerAccountRequest $request, User $user): RedirectResponse
    {
        $emailWasVerified = DB::transaction(function () use ($request, $user): bool {
            $user->forceFill([
                'password' => $request->validated('password'),
                'remember_token' => Str::random(60),
            ])->save();

            return $user->markEmailAsVerified();
        });

        if ($emailWasVerified) {
            event(new Verified($user));
        }

        CustomerAccountActivated::dispatch($user);

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect('/')->with('status', 'Your account is active.');
    }
}
