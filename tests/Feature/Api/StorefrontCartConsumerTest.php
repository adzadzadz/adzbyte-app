<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\StorefrontCredential;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class StorefrontCartConsumerTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private ?Process $server = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
    }

    protected function tearDown(): void
    {
        $this->server?->stop();
        $this->tearDownCatalogDatabase();
        parent::tearDown();
    }

    public function test_an_independent_node_client_builds_replaces_reviews_and_removes_a_real_http_cart(): void
    {
        $service = User::factory()->create();
        $token = $service->createToken('storefront:synthetic-http', StorefrontCredential::abilities())->plainTextToken;
        $category = ProductCategory::create(['name' => 'Synthetic restricted category', 'limit_one_per_cart' => true]);
        foreach ([12500, 15000, PHP_INT_MAX, PHP_INT_MAX] as $index => $amount) {
            $product = Product::factory()->published()->create(['category_id' => $index < 2 ? $category->id : null]);
            $product->prices()->create(['version' => 1, 'amount_minor' => $amount, 'currency' => 'PHP']);
            $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope', 'internal_note' => 'PRIVATE']]);
            $product->is_available = true;
            $product->save();
        }
        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->server = new Process([PHP_BINARY, '-S', $address, base_path('tests/Support/public-catalog-router.php')], base_path(), [
            ...$this->catalogWorkerEnvironment(), 'CATALOG_HTTP_DATABASE' => $this->database, 'CATALOG_HTTP_ALLOW_STOREFRONT' => '1',
            'SANCTUM_STATEFUL_DOMAINS' => 'adzbyte.com',
            'DB_DATABASE' => $this->temporary.'.must-not-use',
        ]);
        $this->server->start();
        $deadline = microtime(true) + 10;
        do {
            $ready = @stream_socket_client('tcp://'.$address, timeout: 0.1);
            if ($ready !== false) {
                fclose($ready);
                break;
            }
            usleep(10000);
        } while ($this->server->isRunning() && microtime(true) < $deadline);
        $this->assertNotFalse($ready, 'Disposable HTTP server did not start.');
        $node = (new ExecutableFinder)->find('node');
        $this->assertNotNull($node);
        $consumer = new Process([$node, base_path('tests/Support/storefront-cart-consumer.mjs'), 'http://'.$address], base_path(), ['CART_HTTP_TOKEN' => $token]);
        $consumer->setTimeout(60);
        $consumer->run();
        $this->assertTrue($consumer->isSuccessful(), $consumer->getOutput().$consumer->getErrorOutput());
        $result = json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $result['result']);
        $this->assertCount(4, $result['checks']);
        $this->assertFileDoesNotExist($this->temporary.'.must-not-use');
    }
}
