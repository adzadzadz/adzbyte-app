<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PackageCheckoutResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'reference' => $this->id, 'status' => $this->status, 'test_mode' => $this->provider_mode === 'test',
            'offer' => ['code' => $this->package_code, 'name' => $this->package_name, 'amount_minor' => $this->amount_minor,
                'currency' => $this->currency, 'quantity' => $this->quantity, 'version' => $this->offer_version],
            'qr_image' => $this->status === 'pending' ? $this->qr_image : null,
            'expires_at' => $this->expires_at?->toIso8601String(),
        ];
    }
}
