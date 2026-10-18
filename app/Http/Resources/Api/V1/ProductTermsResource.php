<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;

final class ProductTermsResource extends ApiResource
{
    public function toArray(Request $request): array
    {
        return array_filter(Arr::only($this->resource, [
            'scope', 'deliverables', 'exclusions', 'customer_obligations', 'fulfillment', 'entitlements',
        ]), is_string(...));
    }
}
