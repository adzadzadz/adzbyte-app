<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Enums\OrderCapability;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Cookie\CookieValuePrefix;
use Illuminate\Encryption\Encrypter;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class OrderReviewTransportTest extends TestCase
{
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

    public function test_independent_management_and_customer_review_consumer_uses_actual_sessions_and_csrf(): void
    {
        $customer = User::factory()->create();
        $customer->assignRole('customer');
        $other = User::factory()->create();
        $other->assignRole('customer');
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic HTTP scope']]);
        $product->is_available = true;
        $product->save();
        $carts = app(CartActions::class);
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($customer, $guest, $cart['public_id']);
        $carts->attach($customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $admin = User::factory()->create();
        $admin->assignRole('administrator');
        $admin->givePermissionTo(array_column(OrderCapability::cases(), 'value'));
        $key = random_bytes(32);
        $cookie = $this->sessionCookie($customer, $key);
        $otherCookie = $this->sessionCookie($other, $key);
        $adminCookie = $this->sessionCookie($admin, $key);
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $origin = 'http://'.$address;
        $env = [...$this->catalogWorkerEnvironment(), 'APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $origin, 'APP_KEY' => 'base64:'.base64_encode($key),
            'CHECKOUT_HTTP_ALLOW' => '1', 'CHECKOUT_HTTP_DATABASE' => $this->database, 'CHECKOUT_HTTP_BROWSER' => '1'];
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
        $consumer = new Process(['node', base_path('tests/Support/order-review-consumer.mjs'), $origin], base_path(), ['CHECKOUT_HTTP_COOKIE' => $cookie, 'CHECKOUT_HTTP_OTHER_COOKIE' => $otherCookie, 'CHECKOUT_HTTP_ADMIN_COOKIE' => $adminCookie]);
        $consumer->setTimeout(40);
        $consumer->run();
        $this->assertTrue($consumer->isSuccessful(), $consumer->getErrorOutput().$consumer->getOutput().$this->server->getErrorOutput());
        $this->assertTrue(json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR)['passed']);
        $this->assertDatabaseCount('orders', 1);
        $this->assertDatabaseCount('order_lines', 3);
        $this->assertDatabaseCount('order_events', 9);
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
