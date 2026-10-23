<?php

namespace App\Enums;

enum OrderCapability: string
{
    case ViewAny = 'ViewAny:Order';
    case View = 'View:Order';
    case Approve = 'Approve:Order';
    case Reject = 'Reject:Order';
    case Clarify = 'Clarify:Order';
    case Message = 'Message:Order';
    case Note = 'Note:Order';
    case Propose = 'Propose:Order';
    case Settings = 'Manage:CommerceSettings';
}
