<?php

namespace Tests\Feature;

use App\Enums\PaymentCapability;
use App\Models\PaymentEvent;
use App\Models\User;
use App\Services\Payments\CheckoutEvent;
use App\Services\Payments\PayMongoSignature;
use Database\Seeders\RoleSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\PaymentEventFixture;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentOperationsTransportTest extends TestCase
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
        $this->admin->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $payload = PaymentEventFixture::payload($this->attempt);
        $body = json_encode($payload, JSON_THROW_ON_ERROR);
        app(PayMongoSignature::class)->verify($body, PaymentEventFixture::signature($body));
        $event = (new PaymentEvent)->forceFill([...CheckoutEvent::parse($body), 'payment_attempt_id' => $this->attempt->id, 'state' => 'received']);
        $event->save();
        $event->forceFill(['state' => 'rejected', 'reason' => 'session_mismatch', 'processed_at' => now()])->save();
        $customer = $this->admin;
        $other = User::factory()->create();
        $other->assignRole('customer');
        $key = app('encrypter')->getKey();
        $cookie = $this->sessionCookie($customer, $key);
        $otherCookie = $this->sessionCookie($other, $key, 'payment-other-csrf');
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $origin = 'http://'.$address;
        $env = [...$this->catalogWorkerEnvironment(), 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $origin, 'APP_KEY' => 'base64:'.base64_encode($key),
            'CHECKOUT_HTTP_PAYMENT_RECONCILE' => '1', 'CHECKOUT_HTTP_ALLOW' => '1', 'CHECKOUT_HTTP_DATABASE' => $this->database];
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
        $consumer = new Process(['node', base_path('tests/Support/payment-operations-consumer.mjs'), $origin], base_path(), ['CHECKOUT_HTTP_COOKIE' => $cookie, 'CHECKOUT_HTTP_OTHER_COOKIE' => $otherCookie, 'PAYMENT_HTTP_REFERENCE' => $this->attempt->public_id, 'PAYMENT_HTTP_EVENT' => $event->provider_event_id, 'PAYMENT_HTTP_OTHER_CSRF' => 'payment-other-csrf']);
        $consumer->setTimeout(PHP_OS_FAMILY === 'Windows' ? 180 : 40);
        $this->runHttpConsumer($consumer);
        $this->assertTrue($consumer->isSuccessful(), $consumer->getErrorOutput().$consumer->getOutput().$this->server->getErrorOutput());
        $this->assertTrue(json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('order_events', 5);
        $this->assertDatabaseCount('payment_resolutions', 1);
        $this->assertDatabaseCount('payment_settlements', 1);
        $this->assertSame('rejected', $event->fresh()->state);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->assertSame('paid', $this->order->fresh()->payment_status);
    }

    private function sessionCookie(User $customer, string $key, ?string $csrf = null): string
    {
        $id = Str::random(40);
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $customer->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Synthetic contract consumer',
            'payload' => base64_encode(json_encode([Auth::guard('web')->getName() => $customer->id, '_token' => $csrf ?? Str::random(40)], JSON_THROW_ON_ERROR)), 'last_activity' => time()]);
        $value = (new Encrypter($key, 'AES-256-CBC'))->encryptString(CookieValuePrefix::create('checkout-contract-session', $key).$id);

        return 'checkout-contract-session='.rawurlencode($value);
    }
}
