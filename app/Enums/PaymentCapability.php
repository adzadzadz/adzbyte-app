<?php

namespace App\Enums;

enum PaymentCapability: string
{
    case ViewAny = 'ViewAny:PaymentAttempt';
    case View = 'View:PaymentAttempt';
    case Reconcile = 'Reconcile:PaymentAttempt';
    case Expire = 'Expire:PaymentAttempt';
    case Recover = 'Recover:PaymentAttempt';
}
