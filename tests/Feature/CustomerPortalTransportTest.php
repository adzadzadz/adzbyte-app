<?php

namespace Tests\Feature;

use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\Portal\CreatesPortal;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CustomerPortalTransportTest extends TestCase
{
    use CreatesPortal, UsesIsolatedCatalogDatabase;

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
        $this->setUpPortal();
        $customer = $this->portalCustomer;
        $other = $this->portalOther;
        $work = $this->portalWork();
        $key = random_bytes(32);
        $cookie = $this->sessionCookie($customer, $key);
        $otherCookie = $this->sessionCookie($other, $key);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $origin = 'http://'.$address;
        $env = [...$this->catalogWorkerEnvironment(), 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $origin, 'APP_KEY' => 'base64:'.base64_encode($key),
            'CHECKOUT_HTTP_ALLOW' => '1', 'CHECKOUT_HTTP_BROWSER' => '1', 'CHECKOUT_HTTP_DATABASE' => $this->database];
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
        $consumer = new Process(['node', base_path('tests/Support/Portal/consumer.mjs'), $origin], base_path(), ['CHECKOUT_HTTP_COOKIE' => $cookie, 'CHECKOUT_HTTP_OTHER_COOKIE' => $otherCookie, 'PORTAL_WORK_REFERENCE' => $work->public_id]);
        $consumer->setTimeout(PHP_OS_FAMILY === 'Windows' ? 180 : 40);
        $this->runHttpConsumer($consumer);
        $this->assertTrue($consumer->isSuccessful(), $consumer->getErrorOutput().$consumer->getOutput().$this->server->getErrorOutput());
        $this->assertTrue(json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed']);
        $this->assertDatabaseCount('support_cases', 1);
        $this->assertDatabaseCount('support_messages', 1);
        $this->assertDatabaseCount('orders', 0);
    }

    private function sessionCookie(User $customer, string $key): string
    {
        $id = Str::random(40);
        DB::table('sessions')->insert(['id' => $id, 'user_id' => $customer->id, 'ip_address' => '127.0.0.1', 'user_agent' => 'Synthetic contract consumer',
            'payload' => base64_encode(json_encode([Auth::guard('web')->getName() => $customer->id, '_token' => Str::random(40)], JSON_THROW_ON_ERROR)), 'last_activity' => time()]);
        $value = (new Encrypter($key, 'AES-256-CBC'))->encryptString(CookieValuePrefix::create('checkout-contract-session', $key).$id);

        return 'checkout-contract-session='.rawurlencode($value);
    }
}
