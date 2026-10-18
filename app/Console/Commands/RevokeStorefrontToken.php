<?php

namespace App\Console\Commands;

use App\Services\Carts\StorefrontCredential;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\PersonalAccessToken;

final class RevokeStorefrontToken extends Command
{
    protected $signature = 'commerce:revoke-storefront-token {id}';

    protected $description = 'Revoke one identified storefront credential without affecting unrelated tokens';

    public function handle(): int
    {
        $id = (string) $this->argument('id');
        $token = ctype_digit($id) ? PersonalAccessToken::find($id) : null;
        if ($token === null || ! str_starts_with($token->name, 'storefront:') || ! is_array($token->abilities)
            || $token->abilities === [] || count(array_filter($token->abilities, is_string(...))) !== count($token->abilities)
            || array_diff($token->abilities, StorefrontCredential::abilities()) !== []) {
            $this->components->error('No matching narrow storefront credential was found.');

            return self::FAILURE;
        }
        DB::transaction(fn () => $token->delete(), 5);
        Log::notice('Storefront credential revoked by console operator.', ['user_id' => $token->tokenable_id, 'token_id' => $token->id]);
        $this->components->info('Storefront credential revoked.');

        return self::SUCCESS;
    }
}
