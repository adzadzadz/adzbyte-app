<?php

namespace App\Enums;

enum StorefrontAbility: string
{
    case CreateCart = 'cart:create';
    case ReadCart = 'cart:read';
    case WriteCart = 'cart:write';
    case HandoffCart = 'cart:handoff';
}
