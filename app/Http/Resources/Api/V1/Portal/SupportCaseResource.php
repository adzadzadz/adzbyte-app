<?php

namespace App\Http\Resources\Api\V1\Portal;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

final class SupportCaseResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return $this->resource;
    }
}
