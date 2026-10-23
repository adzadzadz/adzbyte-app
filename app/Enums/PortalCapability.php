<?php

namespace App\Enums;

enum PortalCapability: string
{
    case ViewWork = 'View:WorkItem';
    case ManageWork = 'Manage:WorkItem';
    case ViewCases = 'View:SupportCase';
    case ManageCases = 'Manage:SupportCase';
    case ReplyCases = 'Reply:SupportCase';
    case NoteCases = 'Note:SupportCase';
    case Categories = 'Manage:SupportCategory';
}
