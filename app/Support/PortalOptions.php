<?php

namespace App\Support;

final class PortalOptions
{
    public const WORK_TYPES = ['project' => 'Project', 'service' => 'Service'];

    public const WORK_STATUSES = ['not_started' => 'Not started', 'in_progress' => 'In progress', 'waiting_for_customer' => 'Waiting for customer', 'on_hold' => 'On hold', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public const CASE_STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'waiting_for_customer' => 'Waiting for customer', 'resolved' => 'Resolved', 'closed' => 'Closed'];

    public const CASE_TYPES = ['request' => 'Request', 'report' => 'Report an issue'];

    public const PRIORITIES = ['low' => 'Low', 'normal' => 'Normal', 'high' => 'High'];

    public static function label(string $value, bool $staff = false): string
    {
        if ($value === 'waiting_for_customer' && ! $staff) {
            return 'Waiting for you';
        }

        return self::WORK_STATUSES[$value] ?? self::CASE_STATUSES[$value] ?? ucfirst(str_replace('_', ' ', $value));
    }
}
