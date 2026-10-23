<?php

declare(strict_types=1);

namespace App\Services\Carts;

use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Validator;

/** Transport adapters construct this only from authenticated identity or cart proof. */
final readonly class CartPrincipal
{
    private function __construct(private ?User $user, private ?string $hash) {}

    public static function guest(#[\SensitiveParameter] string $secret): self
    {
        Validator::make(['secret' => $secret], ['secret' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/D']])->validate();

        return new self(null, hash('sha256', $secret));
    }

    public static function customer(User $user): self
    {
        $principal = new self($user, null);
        $principal->assertValid();

        return $principal;
    }

    /** Only for an existing trusted server session; HTTP proof always uses guest(). */
    public static function fromTrustedSessionHash(#[\SensitiveParameter] string $hash): self
    {
        abort_unless(preg_match('/^[a-f0-9]{64}$/D', $hash), 404);

        return new self(null, $hash);
    }

    public function actor(): ?User
    {
        return $this->user;
    }

    public function credentialHash(): ?string
    {
        return $this->hash;
    }

    public function assertValid(): void
    {
        abort_if($this->user !== null && ! ($this->user->fresh()?->hasRole(UserRole::Customer->value) ?? false), 403);
    }

    public function scope(Builder $query): Builder
    {
        $this->assertValid();

        return $this->user === null
            ? $query->whereNull('user_id')->where('credential_hash', $this->hash)
            : $query->where('user_id', $this->user->id)->whereNull('credential_hash');
    }

    public function allows(Cart $cart): bool
    {
        $this->assertValid();
        if ($cart->merged_into_cart_id !== null) {
            return false;
        }

        return $this->user === null
            ? $cart->user_id === null && is_string($cart->credential_hash) && hash_equals($cart->credential_hash, $this->hash)
            : $cart->user_id === $this->user->id && $cart->credential_hash === null;
    }
}
