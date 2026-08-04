<?php

namespace App\Models;

use App\Enums\ApiIdempotencyStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable([
    'scope_hash',
    'key_hash',
    'request_fingerprint',
    'status',
    'response_status',
    'response_headers',
    'response_body',
    'expires_at',
])]
class ApiIdempotencyKey extends Model
{
    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ApiIdempotencyStatus::class,
            'response_status' => 'integer',
            'response_headers' => 'array',
            'expires_at' => 'immutable_datetime',
        ];
    }
}
