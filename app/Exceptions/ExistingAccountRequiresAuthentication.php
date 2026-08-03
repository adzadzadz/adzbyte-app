<?php

namespace App\Exceptions;

use RuntimeException;

class ExistingAccountRequiresAuthentication extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Authentication is required before checkout can continue.');
    }
}
