<?php

namespace App\Exceptions;

final class PayMongoFailure extends \RuntimeException
{
    public function __construct(public readonly string $reason)
    {
        parent::__construct('The payment provider request could not be verified.');
    }
}
