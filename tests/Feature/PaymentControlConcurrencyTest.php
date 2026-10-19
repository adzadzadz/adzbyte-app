<?php

namespace Tests\Feature;

use App\Models\PaymentControl;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentControlConcurrencyTest extends TestCase
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
    public function test_four_processes_reserve_one_closure_and_send_one_expiry_post(bool $sameCommand): void
    {
        $payload = PaymentEventFixture::payload($this->attempt)['data']['attributes']['data'];
        $payload['attributes']['payments'] = [];
        $payload['attributes']['payment_intent'] = null;
        $remote = $this->temporary.'.control-remote';
        $log = $this->temporary.'.control-posts';
        file_put_contents($remote, 'active');
        file_put_contents($log, '');
        $barrier = $this->temporary.'.control-go';
        $operation = (string) Str::uuid();
        foreach (range(1, 4) as $unused) {
            $input = ['actor' => $this->customer->id, 'order' => $this->order->public_id, 'operation' => $sameCommand ? $operation : (string) Str::uuid(),
                'provider' => ['data' => $payload], 'remote_file' => $remote, 'post_log' => $log];
            $worker = new Process([PHP_BINARY, base_path('tests/Support/payment-event-command.php'), $this->database, json_encode($input, JSON_THROW_ON_ERROR), $barrier, 'control'], base_path(), $this->catalogWorkerEnvironment());
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
        $this->assertDatabaseCount('payment_controls', 1);
        $this->assertSame('confirmed', PaymentControl::sole()->state);
        $this->assertSame("expire\n", file_get_contents($log));
        $this->assertDatabaseCount('payment_settlements', 0);
        $this->assertDatabaseCount('order_events', 5);
        $this->assertSame('expired', $this->attempt->fresh()->state);
        $this->assertSame('cancelled', $this->order->fresh()->status);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertNull($this->order->fresh()->payment_attempt_id);
        $this->assertSame(5, $this->order->fresh()->revision);
    }

    public static function commands(): array
    {
        return [[true], [false]];
    }
}
