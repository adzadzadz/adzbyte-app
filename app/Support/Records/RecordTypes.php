<?php

namespace App\Support\Records;

use App\Models;
use Illuminate\Database\Eloquent\Model;

/** A closed business-record registry. Credentials, sessions and queue internals are excluded. */
final class RecordTypes
{
    public const TYPES = [
        'users' => [Models\User::class, 'Users / customers', ['name', 'email', 'email_verified_at', 'created_at']],
        'roles' => [Models\Role::class, 'Roles', ['name', 'guard_name', 'created_at']],
        'work' => [Models\WorkItem::class, 'Projects / services', ['public_id', 'title', 'type', 'summary', 'status', 'next_step', 'target_date', 'user_id', 'assigned_to', 'revision']],
        'milestones' => [Models\WorkMilestone::class, 'Milestones', ['public_id', 'title', 'target_date', 'completed_at', 'work_item_id']],
        'updates' => [Models\WorkUpdate::class, 'Published work updates', ['body', 'work_item_id', 'actor_id', 'created_at']],
        'cases' => [Models\SupportCase::class, 'Requests / reports', ['public_id', 'subject', 'description', 'type', 'status', 'priority', 'user_id', 'work_item_id', 'assigned_to', 'revision']],
        'support-categories' => [Models\SupportCategory::class, 'Support categories', ['public_id', 'name', 'active']],
        'messages' => [Models\SupportMessage::class, 'Conversation messages', ['body', 'support_case_id', 'actor_id', 'created_at']],
        'notes' => [Models\SupportNote::class, 'Internal support notes', ['body', 'support_case_id', 'actor_id', 'created_at']],
        'portal-history' => [Models\PortalEvent::class, 'Portal audit history', ['action', 'changes', 'work_item_id', 'support_case_id', 'support_category_id', 'actor_id', 'created_at']],
        'products' => [Models\Product::class, 'Products', ['public_id', 'name', 'slug', 'summary', 'status', 'is_available', 'category_id']],
        'product-categories' => [Models\ProductCategory::class, 'Product categories', ['public_id', 'name', 'limit_one_per_cart']],
        'prices' => [Models\ProductPrice::class, 'Price versions', ['public_id', 'product_id', 'version', 'currency', 'amount_minor', 'created_at']],
        'terms' => [Models\ProductTerms::class, 'Terms versions', ['public_id', 'product_id', 'version', 'content', 'created_at']],
        'product-history' => [Models\ProductChange::class, 'Product audit history', ['product_id', 'actor_id', 'action', 'before', 'after', 'created_at']],
        'category-history' => [Models\ProductCategoryChange::class, 'Category audit history', ['product_category_id', 'actor_id', 'action', 'before', 'after', 'created_at']],
        'orders' => [Models\Order::class, 'Orders', ['public_id', 'user_id', 'status', 'approval_status', 'payment_status', 'fulfillment_status', 'revision', 'created_at']],
        'order-revisions' => [Models\OrderRevision::class, 'Order revisions', ['order_id', 'version', 'currency', 'total_minor', 'accepted_at', 'created_at']],
        'order-lines' => [Models\OrderLine::class, 'Order lines', ['order_revision_id', 'product_id', 'snapshot', 'created_at']],
        'order-messages' => [Models\OrderMessage::class, 'Order messages / notes', ['public_id', 'order_id', 'audience', 'sender', 'body', 'actor_id', 'created_at']],
        'order-history' => [Models\OrderEvent::class, 'Order audit history', ['order_id', 'actor_id', 'action', 'before', 'after', 'created_at']],
        'order-proposals' => [Models\OrderProposal::class, 'Order proposals', ['public_id', 'order_id', 'base_revision', 'created_at']],
        'payments' => [Models\PaymentAttempt::class, 'Payments', ['public_id', 'order_id', 'state', 'amount_minor', 'currency', 'livemode', 'created_at']],
        'payment-events' => [Models\PaymentEvent::class, 'Provider events', ['payment_attempt_id', 'provider_event_id', 'type', 'state', 'reason', 'processed_at', 'created_at']],
        'payment-resolutions' => [Models\PaymentResolution::class, 'Payment resolutions', ['public_id', 'payment_attempt_id', 'payment_event_id', 'actor_id', 'original_state', 'original_reason', 'note', 'created_at']],
        'settlements' => [Models\PaymentSettlement::class, 'Payment settlements', ['order_id', 'payment_attempt_id', 'amount_minor', 'currency', 'livemode', 'paid_at']],
        'payment-controls' => [Models\PaymentControl::class, 'Payment controls', ['payment_attempt_id', 'action', 'state', 'created_at']],
        'package-checkouts' => [Models\PackageCheckout::class, 'Package checkouts', ['package_name', 'package_code', 'status', 'amount_minor', 'currency', 'provider_mode', 'paid_at', 'created_at']],
        'package-deliveries' => [Models\PackagePurchaseDelivery::class, 'Package delivery history', ['package_checkout_id', 'phase', 'status', 'attempts', 'last_error_code', 'delivered_at']],
        'commerce-policies' => [Models\CommercePolicyVersion::class, 'Commerce policy versions', ['public_id', 'version', 'actor_id', 'created_at']],
    ];

    /** Parents affect visibility, never physical deletion or financial processing. */
    public const PARENTS = [
        Models\WorkItem::class => ['user_id' => Models\User::class],
        Models\WorkMilestone::class => ['work_item_id' => Models\WorkItem::class],
        Models\WorkUpdate::class => ['work_item_id' => Models\WorkItem::class],
        Models\SupportCase::class => ['user_id' => Models\User::class, 'work_item_id' => Models\WorkItem::class],
        Models\SupportMessage::class => ['support_case_id' => Models\SupportCase::class],
        Models\SupportNote::class => ['support_case_id' => Models\SupportCase::class],
        Models\PortalEvent::class => ['work_item_id' => Models\WorkItem::class, 'support_case_id' => Models\SupportCase::class, 'support_category_id' => Models\SupportCategory::class],
        Models\ProductPrice::class => ['product_id' => Models\Product::class],
        Models\ProductTerms::class => ['product_id' => Models\Product::class],
        Models\ProductChange::class => ['product_id' => Models\Product::class],
        Models\ProductCategoryChange::class => ['product_category_id' => Models\ProductCategory::class],
        Models\Order::class => ['user_id' => Models\User::class],
        Models\OrderRevision::class => ['order_id' => Models\Order::class],
        Models\OrderLine::class => ['order_revision_id' => Models\OrderRevision::class],
        Models\OrderMessage::class => ['order_id' => Models\Order::class],
        Models\OrderEvent::class => ['order_id' => Models\Order::class],
        Models\OrderProposal::class => ['order_id' => Models\Order::class],
        Models\PaymentAttempt::class => ['order_id' => Models\Order::class],
        Models\PaymentEvent::class => ['payment_attempt_id' => Models\PaymentAttempt::class],
        Models\PaymentResolution::class => ['payment_attempt_id' => Models\PaymentAttempt::class],
        Models\PaymentControl::class => ['payment_attempt_id' => Models\PaymentAttempt::class],
        Models\PaymentSettlement::class => ['order_id' => Models\Order::class, 'payment_attempt_id' => Models\PaymentAttempt::class],
        Models\PackagePurchaseDelivery::class => ['package_checkout_id' => Models\PackageCheckout::class],
    ];

    public static function type(Model|string $model): string
    {
        $class = is_string($model) ? $model : $model::class;
        foreach (self::TYPES as $type => [$candidate]) {
            if ($candidate === $class) {
                return $type;
            }
        }
        abort(404);
    }

    public static function definition(string $type): array
    {
        abort_unless(isset(self::TYPES[$type]), 404);

        return self::TYPES[$type];
    }

    public static function label(Model $record): string
    {
        return (string) ($record->getAttribute('title') ?? $record->getAttribute('subject') ?? $record->getAttribute('name') ?? $record->getAttribute('package_name') ?? $record->getAttribute('public_id') ?? mb_substr($record->getAttribute('body') ?? '', 0, 100) ?: '#'.$record->getKey());
    }
}
