<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Product;
use Illuminate\Http\Request;

/** @mixin Product */
class ProductResource extends ApiResource
{
    private const PUBLIC_TERMS = ['scope', 'deliverables', 'exclusions', 'customer_obligations', 'fulfillment', 'entitlements'];

    private bool $includeCategory = false;

    public function withCategory(): static
    {
        $this->includeCategory = true;

        return $this;
    }

    public function toArray(Request $request): array
    {
        $price = $this->currentPrice;
        $terms = $this->currentTerms;
        $media = [];
        foreach (['image', 'thumbnail', 'hero'] as $key) {
            $reference = ($this->media ?? [])[$key] ?? null;
            if ($this->safeImage($reference)) {
                $media[$key] = $reference;
            }
        }
        $content = array_filter(array_intersect_key($terms?->content ?? [], array_flip(self::PUBLIC_TERMS)), is_string(...));

        return [
            'public_id' => $this->public_id,
            'slug' => $this->slug,
            'name' => $this->name,
            'summary' => $this->summary,
            'description' => $this->description,
            'media' => (object) $media,
            'is_available' => $this->is_available && $price !== null && $price->currency === 'PHP'
                && $price->amount_minor > 0 && $terms !== null && $terms->content !== [],
            'price' => $price === null ? null : [
                'version' => $price->version,
                'amount_minor' => (string) $price->amount_minor,
                'currency' => $price->currency,
            ],
            'terms' => $terms === null ? null : ['version' => $terms->version, 'content' => (object) $content],
            ...($this->includeCategory || $request->query('include') === 'category') ? ['category' => $this->category === null ? null : [
                'public_id' => $this->category->public_id,
                'name' => $this->category->name,
                'limit_one_per_cart' => $this->category->limit_one_per_cart,
            ]] : [],
        ];
    }

    private function safeImage(mixed $reference): bool
    {
        if (! is_string($reference) || strlen($reference) > 2048) {
            return false;
        }
        $parts = parse_url($reference);
        $https = is_array($parts) && ($parts['scheme'] ?? null) === 'https'
            && isset($parts['host']) && ! isset($parts['user']) && ! isset($parts['pass'])
            && filter_var($reference, FILTER_VALIDATE_URL);
        $local = preg_match('~^/images/[a-zA-Z0-9/_.-]+$~D', $reference)
            && array_intersect(explode('/', $reference), ['.', '..']) === [];

        return (bool) ($https || $local);
    }
}
