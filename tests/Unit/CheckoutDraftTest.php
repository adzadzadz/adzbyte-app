<?php

namespace Tests\Unit;

use App\Models\User;
use App\Services\Orders\CheckoutDraft;
use Illuminate\Support\Str;
use Tests\TestCase;

class CheckoutDraftTest extends TestCase
{
    public function test_draft_survives_review_without_acceptance_and_cannot_cross_identity_or_retention(): void
    {
        $actor = (new User)->forceFill(['id' => 1]);
        $other = (new User)->forceFill(['id' => 2]);
        $session = app('session')->driver();
        $session->start();
        $service = app(CheckoutDraft::class);
        $operation = (string) Str::uuid();
        $input = ['brief' => ['goal' => 'Synthetic goal', 'scope' => ['bad type'], 'private' => 'not a field'], 'billing' => ['email' => 'buyer@example.test'], 'operation_id' => $operation, 'accept_terms' => true, 'fingerprint' => str_repeat('a', 64)];
        $draft = $service->remember($session, $actor, $input, 90);
        $this->assertSame($operation, $draft['operation_id']);
        $this->assertSame(['goal' => 'Synthetic goal'], $draft['brief']);
        $this->assertArrayNotHasKey('accept_terms', $draft);
        $this->assertArrayNotHasKey('fingerprint', $draft);
        $this->assertSame($draft, $service->restore($session, $actor, 90));
        $this->assertSame([], $service->restore($session, $other, 90, $input)['brief']);
        $this->assertFalse($session->has('commerce.checkout_draft'));
        $service->remember($session, $actor, $input, 90);
        $session->put('commerce.checkout_draft.expires_at', time() - 1);
        $this->assertSame([], $service->restore($session, $actor, 90)['brief']);
        $service->remember($session, $actor, $input, 90);
        $service->forget($session);
        $this->assertFalse($session->has('commerce.checkout_draft'));
        $this->assertFalse($session->has('commerce.checkout_input_owner'));
    }
}
