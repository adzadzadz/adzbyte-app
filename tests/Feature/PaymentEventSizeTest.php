<?php

namespace Tests\Feature;

use App\Actions\Payments\PaymentEventActions;
use App\Services\Payments\PayMongoSignature;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Support\PaymentEventFixture;
use Tests\TestCase;

// Exercise each large request with a fresh PHP process and its normal memory
// limit, rather than the retained state of hundreds of earlier framework tests.
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PaymentEventSizeTest extends TestCase
{
    use RefreshDatabase;

    #[DataProvider('names')]
    public function test_maximum_supported_checkout_event_fits_the_signed_receiver_budget(string $name): void
    {
        config(['services.paymongo.enabled' => true, 'services.paymongo.mode' => 'test', 'services.paymongo.secret_key' => 'sk_test_synthetic_only', 'services.paymongo.webhook_secret' => PaymentEventFixture::SECRET]);
        $lines = array_fill(0, 999, ['name' => str_repeat($name, 255), 'amount' => 100, 'currency' => 'PHP', 'quantity' => 1]);
        $resource = ['id' => 'cs_maximum', 'type' => 'checkout_session', 'attributes' => ['line_items' => $lines, 'payments' => []]];
        $payload = ['data' => ['id' => 'evt_maximum', 'type' => 'event', 'attributes' => ['type' => 'checkout_session.payment.paid', 'livemode' => false, 'created_at' => now()->timestamp, 'data' => $resource]]];
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        $this->assertGreaterThan(262144, strlen($body));
        $this->assertLessThan(8388608, strlen($body));
        app(PayMongoSignature::class)->verify($body, PaymentEventFixture::signature($body));
        $event = app(PaymentEventActions::class)->receive($body, PaymentEventFixture::signature($body));
        $this->assertSame('needs_review', $event->state);
        $this->assertCount(999, $event->evidence['data']['attributes']['line_items']);
    }

    public static function names(): array
    {
        return [['a'], ['𐍈']];
    }

    public function test_oversized_body_is_denied_before_signature_or_persistence(): void
    {
        try {
            app(PaymentEventActions::class)->receive(str_repeat('a', 8388609), '');
            $this->fail('Oversized webhook accepted.');
        } catch (HttpException $exception) {
            $this->assertSame(413, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('payment_events', 0);
    }
}
