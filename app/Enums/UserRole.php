<?php

namespace App\Enums;

enum UserRole: string
{
    case Customer = 'customer';
    case Administrator = 'administrator';
    case SuperAdmin = 'super_admin';
}
