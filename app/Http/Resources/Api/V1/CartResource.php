<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Services\Carts\MinorUnits;
use Illuminate\Http\Request;

class CartResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $cart = $this->resource;
        $subtotal = '0';
        $complete = $cart['subtotal_complete'];
        $publicCategories = [];
        foreach ($cart['lines'] as $line) {
            $product = $line['product'];
            if ($product->status !== ProductStatus::Published) {
                $complete = false;
            } elseif ($product->currentPrice?->currency === 'PHP') {
                $subtotal = MinorUnits::add($subtotal, (string) $product->currentPrice->amount_minor);
            }
            if ($product->status === ProductStatus::Published && $product->category !== null) {
                $publicCategories[] = $product->category->public_id;
            }
        }

        return [
            'public_id' => $cart['public_id'], 'revision' => $cart['revision'],
            'currency' => $cart['currency'], 'subtotal_minor' => $subtotal,
            'subtotal_complete' => $complete, 'selection_valid' => $cart['selection_valid'],
            'requires_review' => $cart['requires_review'], 'category_conflicts' => array_values(array_intersect($cart['category_conflicts'], $publicCategories)),
            'items' => array_map(fn (array $line): array => [
                'product' => $this->product($line['product'], $request),
                'quantity' => 1, 'available' => $line['available'], 'requires_review' => $line['requires_review'],
                'category_conflict' => $line['category_conflict'],
                'quoted_price_version' => $line['quoted_price_version'], 'quoted_terms_version' => $line['quoted_terms_version'],
            ], $cart['lines']),
            'effects' => ['replaced_products' => array_map(fn (array $replaced): array => [
                'public_id' => $replaced['public_id'], 'name' => $replaced['name'],
            ], $cart['effects']['replaced_products'])],
        ];
    }

    private function product(Product $product, Request $request): array
    {
        if ($product->status === ProductStatus::Published) {
            return (new ProductResource($product))->withCategory()->resolve($request);
        }

        return [
            'public_id' => $product->public_id, 'slug' => null, 'name' => 'Unavailable service',
            'summary' => null, 'description' => null, 'media' => (object) [],
            'is_available' => false, 'price' => null, 'terms' => null, 'category' => null,
        ];
    }
}
