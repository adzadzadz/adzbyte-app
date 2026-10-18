<?php

namespace App\Http\Resources\Api\V1;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Http\Request;

final class CartMergeResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        $state = $this->resource;

        return [
            'fingerprint' => $state['fingerprint'],
            'incoming' => (new CartResource($state['source']))->resolve($request),
            'saved' => $state['target'] === null ? null : (new CartResource($state['target']))->resolve($request),
            'conflicts' => array_map(function (array $group): array {
                $public = $group['products']->first(fn (Product $product): bool => $product->status === ProductStatus::Published);

                return ['key' => $group['key'], 'category' => $public?->category?->name ?? 'Service category',
                    'products' => $group['products']->map(fn (Product $product): array => [
                        'public_id' => $product->public_id,
                        'name' => $product->status === ProductStatus::Published ? $product->name : 'Unavailable service',
                    ])->all()];
            }, $state['groups']),
        ];
    }
}
