<?php

namespace App\Http\Controllers\Auth;

use App\Http\Requests\Auth\RegisterCustomerRequest;
use App\Jobs\RegisterPublicCustomer;
use App\Services\Carts\CommerceOrigins;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class RegisterCustomerController
{
    public const MESSAGE = 'Check your email for account instructions. If you already have an active account, sign in or reset your password.';

    public function show(): Response
    {
        return response()->view('auth.register', ['website' => CommerceOrigins::storefront()])->header('Cache-Control', 'no-store, private');
    }

    public function bootstrap(Request $request): JsonResponse
    {
        abort_unless($request->query() === [], 422);

        return response()->json(['data' => ['csrf_token' => $request->session()->token(), 'submit' => '/api/v1/registration'], 'meta' => ['api_version' => 'v1']])->header('Cache-Control', 'no-store, private');
    }

    public function store(RegisterCustomerRequest $request): JsonResponse|RedirectResponse
    {
        RegisterPublicCustomer::dispatch($request->validated('name'), $request->validated('email'))->onConnection('database')->afterCommit();

        return $request->is('api/*')
            ? response()->json(['data' => ['message' => self::MESSAGE], 'meta' => ['api_version' => 'v1']], 202)->header('Cache-Control', 'no-store, private')
            : redirect()->route('registration.show')->with('registration_sent', self::MESSAGE)->header('Cache-Control', 'no-store, private');
    }
}
