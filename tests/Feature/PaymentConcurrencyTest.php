<?php

namespace Tests\Feature;

use App\Models\PaymentAttempt;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentOrder;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentConcurrencyTest extends TestCase
{
    use CreatesPaymentOrder, UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->createPaymentOrder();
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
    public function test_four_processes_reserve_one_attempt_and_send_exactly_one_creation_post(bool $sameCommand): void
    {
        $operation = (string) Str::uuid();
        $barrier = $this->temporary.'.payment-go';
        foreach (range(1, 4) as $unused) {
            $worker = new Process([PHP_BINARY, base_path('tests/Support/payment-command.php'), $this->database, (string) $this->customer->id, $this->order->public_id,
                $sameCommand ? $operation : (string) Str::uuid(), $barrier], base_path(), $this->catalogWorkerEnvironment());
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
        $passed = array_values(array_filter($results, fn ($result) => $result['status'] === 'passed'));
        $this->assertCount($sameCommand ? 4 : 1, $passed);
        $this->assertCount(1, array_unique(array_column($passed, 'reference')));
        if (! $sameCommand) {
            $this->assertCount(3, array_filter($results, fn ($result) => $result['status'] === 'denied' && $result['code'] === 409));
        }
        $this->assertSame("create\n", file_get_contents($barrier.'.posts'));
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->assertSame('active', PaymentAttempt::sole()->state);
        $this->assertSame('pending', $this->order->fresh()->payment_status);
        $this->assertSame(3, $this->order->fresh()->revision);
    }

    public static function commands(): array
    {
        return [[true], [false]];
    }
}
