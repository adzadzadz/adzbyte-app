<?php

namespace App\Services\Payments;

final class PayMongoSignature
{
    public function verify(string $body, string $header): void
    {
        app(PayMongoClient::class)->ready();
        $secret = config('services.paymongo.webhook_secret');
        abort_unless(is_string($secret) && strlen($secret) >= 16, 503);
        abort_if(strlen($header) > 512, 401);
        $values = [];
        foreach (explode(',', $header) as $part) {
            $pair = explode('=', trim($part), 2);
            abort_unless(count($pair) === 2 && in_array($pair[0], ['t', 'te', 'li'], true) && ! array_key_exists($pair[0], $values), 401);
            $values[$pair[0]] = $pair[1];
        }
        abort_unless(count($values) === 3 && preg_match('/^[1-9][0-9]{9,10}$/D', $values['t'] ?? '')
            && preg_match('/^[a-f0-9]{64}$/D', $values['te'] ?? '') && ($values['li'] ?? null) === ''
            && abs(now()->timestamp - (int) $values['t']) <= 300
            && hash_equals(hash_hmac('sha256', $values['t'].'.'.$body, $secret), $values['te']), 401);
    }
}
