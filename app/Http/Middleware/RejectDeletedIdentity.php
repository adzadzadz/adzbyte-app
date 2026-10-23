<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Records\RecordVisibility;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RejectDeletedIdentity
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user instanceof User) {
            if (RecordVisibility::isDeleted($user)) {
                if ($request->hasSession() && Auth::guard('web')->id() === $user->id) {
                    Auth::guard('web')->logout();
                    $request->session()->invalidate();
                    $request->session()->regenerateToken();
                }
                abort(403, 'This account is deleted. Contact an administrator to restore it.');
            }
        }

        return $next($request);
    }
}
