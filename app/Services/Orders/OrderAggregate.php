<?php

namespace App\Services\Orders;

use App\Models\CommercePolicyVersion;
use App\Models\Order;
use App\Models\OrderLine;
use App\Models\OrderRevision;

final class OrderAggregate
{
    /** Current reads after an order lock, including under MySQL repeatable read. */
    public static function hydrate(Order $order): Order
    {
        $revision = OrderRevision::where('order_id', $order->id)->orderByDesc('version')->lockForUpdate()->firstOrFail();
        $revision->setRelation('lines', OrderLine::where('order_revision_id', $revision->id)->orderBy('id')->lockForUpdate()->get());
        $revision->setRelation('policyVersion', CommercePolicyVersion::whereKey($revision->policy_version_id)->lockForUpdate()->firstOrFail());
        $order->setRelation('currentRevision', $revision);

        return $order;
    }
}
