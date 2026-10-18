<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Models\CartHandoff;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\StorefrontCredential;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;
use Tests\Support\UsesIsolatedCatalogDatabase;
use Tests\TestCase;

class CartHandoffConcurrencyTest extends TestCase
{
    use UsesIsolatedCatalogDatabase;

    private array $workers = [];

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCatalogDatabase();
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

    public function test_four_simultaneous_identical_mints_issue_one_transfer_and_receipt(): void
    {
        $results = $this->race('mint');
        $this->assertSame(['passed'], array_values(array_unique(array_column($results, 'status'))));
        $this->assertCount(1, array_unique(array_column($results, 'transfer_hash')));
        $this->assertDatabaseCount('cart_handoffs', 1);
        $this->assertDatabaseCount('cart_changes', 2);
    }

    public function test_four_simultaneous_consumers_grant_access_exactly_once(): void
    {
        $results = $this->race('consume');
        $this->assertCount(1, array_filter($results, fn ($row) => $row['status'] === 'passed'));
        $denied = array_values(array_filter($results, fn ($row) => $row['status'] === 'denied'));
        $this->assertCount(3, $denied);
        $this->assertSame([410, 410, 410], array_column($denied, 'code'));
        $this->assertNotNull(CartHandoff::firstOrFail()->consumed_at);
    }

    private function race(string $command): array
    {
        $secret = bin2hex(random_bytes(32));
        $principal = CartPrincipal::guest($secret);
        $cart = app(CartActions::class)->create($principal, (string) Str::uuid());
        $issuer = User::factory()->create()->createToken('storefront:concurrency', StorefrontCredential::abilities())->accessToken;
        $operation = (string) Str::uuid();
        if ($command === 'consume') {
            $link = app(CartHandoffActions::class)->mint($principal, $cart['public_id'], $issuer, $operation);
            $secret = parse_url($link['url'], PHP_URL_FRAGMENT);
        }
        $barrier = $this->temporary.'.go';
        for ($i = 0; $i < 4; $i++) {
            $worker = new Process([
                PHP_BINARY, base_path('tests/Support/cart-command.php'), $this->database, $secret, $command,
                $cart['public_id'], (string) $issuer->id, $operation, $barrier,
            ], base_path(), $this->catalogWorkerEnvironment());
            $worker->setTimeout(20);
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
            $this->assertTrue(json_validate($worker->getOutput()), $worker->getErrorOutput().$worker->getOutput());
            $results[] = json_decode($worker->getOutput(), true, flags: JSON_THROW_ON_ERROR);
        }

        return $results;
    }
}
