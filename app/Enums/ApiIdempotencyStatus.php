<?php

namespace App\Enums;

enum ApiIdempotencyStatus: string
{
    case Pending = 'pending';
    case Completed = 'completed';
}
