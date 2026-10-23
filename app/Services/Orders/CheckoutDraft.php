<?php

namespace App\Services\Orders;

use App\Models\User;
use Illuminate\Contracts\Session\Session;
use Illuminate\Support\Str;

/** Short-lived, owner-bound form recovery in the existing private session. */
final class CheckoutDraft
{
    private const KEY = 'commerce.checkout_draft';

    public function remember(Session $session, User $actor, array $input, int $days): array
    {
        $draft = ['user_id' => $actor->id, 'updated_at' => time(), 'expires_at' => time() + $days * 86400,
            'operation_id' => is_string($input['operation_id'] ?? null) && Str::isUuid($input['operation_id']) ? $input['operation_id'] : (string) Str::uuid(),
            'brief' => [], 'billing' => []];
        foreach (['brief' => ['goal' => 2000, 'scope' => 10000, 'website' => 2048], 'billing' => ['name' => 255, 'email' => 255, 'country' => 2, 'address' => 2000, 'company' => 255, 'phone' => 50]] as $group => $fields) {
            foreach ($fields as $field => $limit) {
                $value = $input[$group][$field] ?? null;
                if (is_string($value) && mb_check_encoding($value, 'UTF-8') && mb_strlen($value) <= $limit) {
                    $draft[$group][$field] = $value;
                }
            }
        }
        // Acceptance and fingerprint always come from a fresh rendered review.
        $session->put(self::KEY, $draft);
        $session->put('commerce.checkout_input_owner', $actor->id);

        return $draft;
    }

    public function restore(Session $session, User $actor, int $days, array $old = []): array
    {
        if ($old !== [] && $session->get('commerce.checkout_input_owner') === $actor->id) {
            return $this->remember($session, $actor, $old, $days);
        }
        $draft = $session->get(self::KEY);
        if (! is_array($draft) || ($draft['user_id'] ?? null) !== $actor->id || ($draft['expires_at'] ?? 0) <= time()
            || ($draft['updated_at'] ?? 0) + $days * 86400 <= time()) {
            $this->forget($session);

            return ['operation_id' => (string) Str::uuid(), 'brief' => [], 'billing' => []];
        }

        return $draft;
    }

    public function forget(Session $session): void
    {
        $session->forget([self::KEY, 'commerce.checkout_input_owner']);
    }
}
