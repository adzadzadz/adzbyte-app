<?php

declare(strict_types=1);

namespace App\Services\Carts;

/** Server-only grant returned after a committed handoff consume. */
final readonly class CartGrant
{
    public function __construct(public string $reference, #[\SensitiveParameter] private string $hash) {}

    public function authorityHash(): string
    {
        return $this->hash;
    }
}
