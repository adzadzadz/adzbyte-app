<?php

namespace Tests\Feature;

use App\Enums\PaymentCapability;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\Process\Process;
use Tests\Support\CreatesPaymentAttempt;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PaymentControlTransportTest extends TestCase
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

    #[DataProvider('actors')]
    public function test_independent_node_consumer_uses_real_session_csrf_and_openapi_for_customer_or_admin_control(bool $admin): void
    {
        $this->createPaymentAttempt();
        $this->admin->givePermissionTo(array_column(PaymentCapability::cases(), 'value'));
        $customer = $admin ? $this->admin : $this->customer;
        $remote = $this->temporary.'.payment-remote';
        file_put_contents($remote, json_encode(['status' => 'active'], JSON_THROW_ON_ERROR));
        chmod($remote, 0600);
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
            'CHECKOUT_HTTP_PAYMENT_CONTROL' => '1', 'CHECKOUT_HTTP_PAYMENT_REMOTE' => $remote, 'CHECKOUT_HTTP_ALLOW' => '1', 'CHECKOUT_HTTP_DATABASE' => $this->database];
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
        $consumer = new Process(['node', base_path('tests/Support/payment-control-consumer.mjs'), $origin], base_path(), ['CHECKOUT_HTTP_COOKIE' => $cookie, 'CHECKOUT_HTTP_OTHER_COOKIE' => $otherCookie, 'PAYMENT_HTTP_REFERENCE' => $this->order->public_id, 'PAYMENT_HTTP_ATTEMPT' => $this->attempt->public_id, 'PAYMENT_HTTP_ADMIN' => $admin ? '1' : '0', 'PAYMENT_HTTP_OTHER_CSRF' => 'payment-other-csrf']);
        $consumer->setTimeout(40);
        $consumer->run();
        $this->assertTrue($consumer->isSuccessful(), $consumer->getErrorOutput().$consumer->getOutput().$this->server->getErrorOutput());
        $this->assertTrue(json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 1);
        $this->assertDatabaseCount('order_events', 5);
        $this->assertDatabaseCount('payment_controls', 1);
        $this->assertSame('expired', $this->attempt->fresh()->state);
        $this->assertDatabaseCount('payment_attempts', 1);
        $this->assertDatabaseCount('payment_commands', 1);
        $this->assertSame('unpaid', $this->order->fresh()->payment_status);
        $this->assertSame($admin ? 'open' : 'cancelled', $this->order->fresh()->status);
    }

    public static function actors(): array
    {
        return [[false], [true]];
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
