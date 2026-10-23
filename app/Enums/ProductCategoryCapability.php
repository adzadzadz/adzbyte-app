<?php

namespace App\Enums;

enum ProductCategoryCapability: string
{
    case ViewAny = 'ViewAny:ProductCategory';
    case View = 'View:ProductCategory';
    case Create = 'Create:ProductCategory';
    case Update = 'Update:ProductCategory';
}
