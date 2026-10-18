<?php

namespace Tests\Feature;

use App\Enums\PaymentCapability;
use App\Models\PaymentEvent;
use App\Services\Payments\CheckoutEvent;
use App\Services\Payments\PayMongoSignature;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentResolutionConcurrencyTest extends TestCase
{
    use CreatesPaymentAttempt, UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->createPaymentAttempt();
    }

    protected function tearDown(): void
    {
        foreach ($this->workers as $worker) {
            if ($worker->isRunning()) {
                $worker->stop();
            }
        }
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    #[DataProvider('commands')]
    public function test_four_processes_reconcile_one_receipt_without_duplicate_settlement_or_resolution(bool $sameCommand): void
    {
        $this->admin->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $payload = PaymentEventFixture::payload($this->attempt);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        app(PayMongoSignature::class)->verify($body, PaymentEventFixture::signature($body));
        $event = (new PaymentEvent)->forceFill([...CheckoutEvent::parse($body), 'payment_attempt_id' => $this->attempt->id, 'state' => 'received']);
        $event->save();
        $event->forceFill(['state' => 'rejected', 'reason' => 'session_mismatch', 'processed_at' => now()])->save();
        $barrier = $this->temporary.'.reconcile-go';
        $operation = (string) Str::uuid();
        foreach (range(1, 4) as $unused) {
            $input = ['actor' => $this->admin->id, 'attempt' => $this->attempt->public_id, 'event' => $event->provider_event_id,
                'operation' => $sameCommand ? $operation : (string) Str::uuid(), 'provider' => ['data' => $payload['data']['attributes']['data']]];
            $worker = new Process([PHP_BINARY, base_path('tests/Support/payment-event-command.php'), $this->database, json_encode($input, JSON_THROW_ON_ERROR), $barrier, 'reconcile'], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(25);
            $worker->start();
            $this->workers[] = $worker;
        }
        $deadline = microtime(true) + 15;
        while (count(glob($barrier.'.*.ready')) < 4 && microtime(true) < $deadline) {
            foreach ($this->workers as $worker) {
                if ($worker->isTerminated()) {
                    $this->fail($worker->getErrorOutput().$worker->getOutput());
                }
            }
            usleep(1000);
        }
        $this->assertCount(4, glob($barrier.'.*.ready'));
        touch($barrier);
        $results = [];
        foreach ($this->workers as $worker) {
            $worker->wait();
            $this->assertTrue($worker->isSuccessful(), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }
        $this->assertCount($sameCommand ? 4 : 1, array_filter($results, fn ($result) => $result['status'] === 'passed'), json_encode($results));
        $this->assertCount($sameCommand ? 0 : 3, array_filter($results, fn ($result) => $result['status'] === 'denied' && $result['code'] === 409));
        $this->assertDatabaseCount('payment_resolutions', 1);
        $this->assertSame('rejected', $event->fresh()->state);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('order_events', 5);
        $this->assertSame('paid', $this->attempt->fresh()->state);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(4, $this->order->fresh()->revision);
    }

    public static function commands(): array
    {
        return [[true], [false]];
    }
}
