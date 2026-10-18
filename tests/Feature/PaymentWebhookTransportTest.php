<?php

namespace Tests\Feature;

use Database\Seeders\RoleSeeder;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentWebhookTransportTest extends TestCase
{
    use CreatesPaymentAttempt;
    use UsesIsolatedCatalogDatabase;

    private ?Process $server = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
        $this->seed(RoleSeeder::class);
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_independent_node_consumer_uses_real_encrypted_sessions_csrf_and_openapi_over_http(): void
    {
        $this->createPaymentAttempt();
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $origin = 'http://'.$address;
        $env = [...$this->catalogWorkerEnvironment(), 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $origin, 'APP_KEY' => config('app.key'),
            'CHECKOUT_HTTP_PAYMENT_FAKE' => '1', 'CHECKOUT_HTTP_ALLOW' => '1', 'CHECKOUT_HTTP_DATABASE' => $this->database];
        $this->server = new Process([PHP_BINARY, '-S', $address, base_path('tests/Support/checkout-router.php')], base_path(), $env);
        $this->server->start();
        $deadline = microtime(true) + 10;
        do {
            $connection = @fsockopen('127.0.0.1', (int) substr(strrchr($address, ':'), 1));
            if ($connection) {
                fclose($connection);
                break;
            }
            if ($this->server->isTerminated()) {
                $this->fail($this->server->getErrorOutput());
            }
            usleep(1000);
        } while (microtime(true) < $deadline);
        $consumer = new Process(['node', base_path('tests/Support/payment-webhook-consumer.mjs'), $origin], base_path(), ['PAYMENT_HTTP_EVENT' => json_encode(PaymentEventFixture::payload($this->attempt), JSON_THROW_ON_ERROR)]);
        $consumer->setTimeout(40);
        $consumer->run();
        $this->assertTrue($consumer->isSuccessful(), $consumer->getErrorOutput().$consumer->getOutput().$this->server->getErrorOutput());
        $this->assertTrue(json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('order_events', 4);
        $this->assertDatabaseCount('payment_events', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }
}
