<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentEventConcurrencyTest extends TestCase
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
    public function test_four_processes_confirm_one_payment_without_duplicate_financial_transitions(bool $sameCommand): void
    {
        $payload = PaymentEventFixture::payload($this->attempt);
        $barrier = $this->temporary.'.payment-go';
        foreach (range(1, 4) as $unused) {
            $current = $payload;
            if (! $sameCommand) {
                $current['data']['id'] = 'evt_worker'.$unused;
            }
            $worker = new Process([PHP_BINARY, base_path('tests/Support/payment-event-command.php'), $this->database, json_encode($current, JSON_THROW_ON_ERROR), $barrier], base_path(), $this->catalogWorkerEnvironment());
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
        $this->assertCount(4, array_filter($results, fn ($result) => $result['status'] === 'passed' && $result['state'] === 'applied'));
        $this->assertDatabaseCount('payment_events', $sameCommand ? 1 : 4);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('order_events', 4);
        $this->assertSame('paid', $this->attempt->fresh()->state);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
        $this->assertSame(4, $this->order->fresh()->revision);
    }

    public static function commands(): array
    {
        return [[true], [false]];
    }
}
