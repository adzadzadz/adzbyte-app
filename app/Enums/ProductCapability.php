<?php

namespace App\Enums;

enum ProductCapability: string
{
    case ViewAny = 'ViewAny:Product';
    case View = 'View:Product';
    case Create = 'Create:Product';
    case Update = 'Update:Product';
    case Publish = 'Publish:Product';
    case Unpublish = 'Unpublish:Product';
    case SetAvailability = 'SetAvailability:Product';
    case Archive = 'Archive:Product';
    case Restore = 'Restore:Product';
    case RevisePrice = 'RevisePrice:Product';
    case ReviseTerms = 'ReviseTerms:Product';
}
