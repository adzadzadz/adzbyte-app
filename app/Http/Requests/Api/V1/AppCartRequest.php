<?php

namespace App\Http\Requests\Api\V1;

use App\Services\Carts\CartPrincipal;
use App\Services\Carts\CartSession;

class AppCartRequest extends StorefrontCartRequest
{
    protected bool $requiresProof = false;

    public function principal(): CartPrincipal
    {
        return app(CartSession::class)->principal($this->session());
    }

    public function reference(): string
    {
        return app(CartSession::class)->reference($this->session());
    }
}
