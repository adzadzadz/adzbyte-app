<?php

namespace App\Services\Carts;

use App\Enums\ProductStatus;
use App\Models\Cart;
use App\Models\CartItem;
use Illuminate\Support\Collection;

/** Internal result. HTTP adapters must project product models with Resources. */
final class CartSnapshot
{
    public static function build(Cart $cart, Collection $items): array
    {
        $restricted = $items->filter(fn (CartItem $item): bool => $item->product->category?->limit_one_per_cart === true)
            ->groupBy(fn (CartItem $item): string => $item->product->category->public_id)
            ->filter(fn (Collection $group): bool => $group->count() > 1)->keys()->all();
        $subtotal = '0';
        $complete = true;
        $review = false;
        $valid = $items->isNotEmpty() && $restricted === [];
        $lines = [];
        foreach ($items as $item) {
            $product = $item->product;
            $price = $product->currentPrice;
            $terms = $product->currentTerms;
            $available = $product->status === ProductStatus::Published && $product->is_available
                && $price !== null && $price->amount_minor > 0 && $price->currency === 'PHP'
                && $terms !== null && $terms->content !== [];
            $changed = $price?->id !== $item->quoted_price_id || $terms?->id !== $item->quoted_terms_id;
            $conflict = in_array($product->category?->public_id, $restricted, true);
            if ($price !== null && $price->currency === 'PHP') {
                $subtotal = MinorUnits::add($subtotal, (string) $price->amount_minor);
            } else {
                $complete = false;
            }
            $review = $review || $changed;
            $valid = $valid && $available && ! $changed;
            $lines[] = [
                'product' => $product, 'quantity' => 1, 'available' => $available,
                'requires_review' => $changed, 'category_conflict' => $conflict,
                'quoted_price_version' => $item->quotedPrice->version,
                'quoted_terms_version' => $item->quotedTerms->version,
            ];
        }

        return [
            'public_id' => $cart->public_id, 'revision' => $cart->revision, 'lines' => $lines,
            'currency' => 'PHP', 'subtotal_minor' => $subtotal, 'subtotal_complete' => $complete,
            'requires_review' => $review, 'category_conflicts' => $restricted, 'selection_valid' => $valid,
        ];
    }
}
