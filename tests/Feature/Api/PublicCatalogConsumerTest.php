<?php

namespace Tests\Feature\Api;

use App\Models\Product;
use App\Models\ProductCategory;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class PublicCatalogConsumerTest extends TestCase
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

    public function test_a_standalone_javascript_consumer_reads_the_real_http_contract(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/public-product.json')), true, flags: JSON_THROW_ON_ERROR);
        Product::factory()->create(['name' => 'Private draft']);
        Product::factory()->create(['name' => 'Private archive', 'status' => 'archived']);
        $category = ProductCategory::create(['name' => 'Synthetic category', 'limit_one_per_cart' => true]);
        $product = Product::factory()->published()->create(['category_id' => $category->id, ...array_intersect_key($fixture, array_flip(['public_id', 'name', 'slug', 'summary', 'description', 'media']))]);
        $product->prices()->create(['version' => 1, 'amount_minor' => PHP_INT_MAX, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope', 'internal_note' => 'PRIVATE']]);
        $product->is_available = true;
        $product->save();
        Product::factory()->published()->create(['name' => 'Synthetic unpriced listing']);

        $socket = stream_socket_server('tcp://127.0.0.1:0');
        $this->assertIsResource($socket);
        $address = stream_socket_get_name($socket, false);
        fclose($socket);
        $this->server = new Process([PHP_BINARY, '-S', $address, base_path('tests/Support/public-catalog-router.php')], base_path(), [
            ...$this->catalogWorkerEnvironment(), 'CATALOG_HTTP_DATABASE' => $this->database,
            // Prove boot-time cache/session resolution cannot retain an
            // inherited development database before the fixture override.
            'CACHE_STORE' => 'database', 'SESSION_DRIVER' => 'database',
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
        $this->assertNotFalse($ready, 'Disposable HTTP server did not start: '.$this->server->getErrorOutput());
        $node = (new ExecutableFinder)->find('node');
        $this->assertNotNull($node, 'Node.js is required for the HTTP consumer gate.');
        $consumer = new Process([$node, base_path('tests/Support/public-catalog-consumer.mjs'), 'http://'.$address], base_path());
        $consumer->setTimeout(30);
        $this->runHttpConsumer($consumer);
        $this->assertTrue($consumer->isSuccessful(), $consumer->getOutput().$consumer->getErrorOutput());
        $result = json_decode($consumer->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('passed', $result['result']);
        $this->assertSame(2, $result['products']);
        $this->assertSame(2, $result['pages']);
        $this->assertFileDoesNotExist($this->temporary.'.must-not-use');
    }
}
