<?php

namespace App\Http\Resources\Api\V1\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class WorkResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // PortalRead constructs the explicit customer-safe projection.
        return $this->resource;
    }
}
