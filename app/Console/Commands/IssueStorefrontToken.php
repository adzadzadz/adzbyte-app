<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Carts\StorefrontCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class IssueStorefrontToken extends Command
{
    protected $signature = 'commerce:storefront-token {email} {--create : Create a new dedicated service identity} {--name=storefront} {--days=90} {--abilities=cart:create,cart:read,cart:write,cart:handoff}';

    protected $description = 'Issue a narrow server-only cart credential for an unprivileged service identity';

    public function handle(): int
    {
        try {
            $email = strtolower(trim((string) $this->argument('email')));
            $abilities = array_map('trim', explode(',', (string) $this->option('abilities')));
            $days = (string) $this->option('days');
            Validator::make(['email' => $email, 'name' => $this->option('name'), 'days' => $days, 'abilities' => $abilities], [
                'email' => ['required', 'email', 'max:255'], 'name' => ['required', 'string', 'max:100'],
                'days' => ['required', 'integer', 'min:1', 'max:365'],
                'abilities' => ['required', 'array', 'min:1'], 'abilities.*' => ['required', 'string', 'distinct', Rule::in(StorefrontCredential::abilities())],
            ])->validate();
            $token = DB::transaction(function () use ($email, $abilities, $days) {
                $user = User::whereRaw('LOWER(email) = ?', [$email])->lockForUpdate()->first();
                if ($this->option('create')) {
                    if ($user !== null) {
                        throw ValidationException::withMessages(['email' => 'The identity already exists; no account was changed.']);
                    }
                    $user = (new User)->forceFill(['name' => 'Storefront API', 'email' => $email, 'email_verified_at' => now(), 'password' => Str::random(96)]);
                    $user->save();
                }
                if ($user === null || ! $user->hasVerifiedEmail() || $user->roles()->exists() || $user->permissions()->exists()) {
                    throw ValidationException::withMessages(['email' => 'Use a verified dedicated identity with no roles or permissions.']);
                }

                return $user->createToken('storefront:'.$this->option('name'), $abilities, now()->addDays((int) $days));
            });
            Log::notice('Storefront credential issued by console operator.', ['user_id' => $token->accessToken->tokenable_id, 'token_id' => $token->accessToken->id, 'expires_at' => $token->accessToken->expires_at->toIso8601String()]);
            $this->components->info('Store this credential server-side. It is shown once; never put it in a browser bundle.');
            $this->line($token->plainTextToken);

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            foreach ($exception->errors() as $messages) {
                $this->components->error($messages[0]);
            }

            return self::FAILURE;
        }
    }
}
