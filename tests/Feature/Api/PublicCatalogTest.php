<?php

namespace Tests\Feature\Api;

use App\Actions\Catalog\CatalogActions;
use App\Enums\ProductCapability;
use App\Enums\ProductStatus;
use App\Enums\UserRole;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class PublicCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_empty_catalog_is_public_and_versioned(): void
    {
        $this->getJson('/api/v1/products')->assertOk()->assertExactJson([
            'data' => [],
            'meta' => ['api_version' => 'v1', 'current_page' => 1, 'per_page' => 20, 'total' => 0, 'last_page' => 1],
            'links' => ['next' => null, 'prev' => null],
        ]);
    }

    public function test_public_reads_do_not_start_a_session_or_set_cookies_for_first_party_origins(): void
    {
        config(['sanctum.stateful' => ['localhost'], 'session.driver' => 'database']);
        $product = $this->offer();
        foreach (['/api/v1/products', '/api/v1/products/'.$product->public_id] as $uri) {
            $this->getJson($uri, ['Origin' => 'http://localhost'])->assertOk()->assertHeaderMissing('Set-Cookie');
        }
        $this->assertDatabaseCount('sessions', 0);
    }

    public function test_only_published_records_are_visible_even_to_authenticated_administrators(): void
    {
        $draft = Product::factory()->create();
        $archived = Product::factory()->create(['status' => 'archived']);
        $published = Product::factory()->published()->create();
        $this->seed(RoleSeeder::class);
        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::SuperAdmin->value);
        $this->actingAs($administrator);
        $this->getJson('/api/v1/products')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.public_id', $published->public_id);
        foreach ([$draft->public_id, $archived->public_id, (string) Str::ulid(), '1', '../secret'] as $reference) {
            $this->getJson('/api/v1/products/'.$reference)->assertNotFound()->assertJsonPath('error.code', 'not_found');
        }
    }

    public function test_public_projection_excludes_internal_fields_and_preserves_exact_money(): void
    {
        $product = $this->offer([
            'description' => '<script>text only</script>',
            'media' => ['image' => '/images/synthetic.png', 'hero' => 'javascript:alert(1)', 'thumbnail' => 'https://user:secret@example.test/a.png', 'internal' => 'https://example.test/private', 'structured' => ['secret' => 'hidden']],
        ], PHP_INT_MAX);
        $product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'Public scope', 'internal_note' => 'SECRET', 'entitlements' => ['internal' => 'SECRET']]]);
        $data = $this->getJson('/api/v1/products/'.$product->public_id)->assertOk()->json('data');
        $this->assertSame([
            'public_id' => $product->public_id,
            'slug' => $product->slug,
            'name' => $product->name,
            'summary' => $product->summary,
            'description' => '<script>text only</script>',
            'media' => ['image' => '/images/synthetic.png'],
            'is_available' => true,
            'price' => ['version' => 1, 'amount_minor' => (string) PHP_INT_MAX, 'currency' => 'PHP'],
            'terms' => ['version' => 2, 'content' => ['scope' => 'Public scope']],
        ], $data);
        $this->getJson('/api/v1/products')->assertJsonPath('data.0', $data);
    }

    public function test_unset_and_zero_prices_remain_distinct_and_unavailable(): void
    {
        $unset = Product::factory()->published()->create();
        $zero = Product::factory()->published()->create();
        $zero->prices()->create(['version' => 1, 'amount_minor' => 0, 'currency' => 'PHP']);
        $this->getJson('/api/v1/products/'.$unset->public_id)->assertOk()->assertJsonPath('data.price', null)->assertJsonPath('data.terms', null)->assertJsonPath('data.media', [])->assertJsonPath('data.is_available', false);
        $this->getJson('/api/v1/products/'.$zero->public_id)->assertOk()->assertJsonPath('data.price.amount_minor', '0')->assertJsonPath('data.is_available', false);
    }

    public function test_pagination_is_stable_bounded_and_filtered_without_exposing_other_states(): void
    {
        Product::factory()->count(2)->create();
        $first = $this->offer();
        Product::factory()->published()->create();
        $last = $this->offer();
        $this->getJson('/api/v1/products?per_page=1&available=1')->assertOk()
            ->assertJsonPath('data.0.public_id', $first->public_id)->assertJsonPath('meta.total', 2)
            ->assertJsonPath('links.next', '/api/v1/products?per_page=1&available=1&page=2');
        $this->getJson('/api/v1/products?per_page=1&available=1&page=2')->assertOk()
            ->assertJsonPath('data.0.public_id', $last->public_id)->assertJsonPath('links.next', null)
            ->assertJsonPath('links.prev', '/api/v1/products?per_page=1&available=1&page=1');
        $this->getJson('/api/v1/products?available=0')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/v1/products?page=10000')->assertOk()->assertJsonCount(0, 'data');
    }

    #[DataProvider('invalidQueries')]
    public function test_invalid_query_controls_are_rejected(string $query): void
    {
        $this->getJson('/api/v1/products?'.$query)->assertUnprocessable()->assertJsonPath('error.code', 'validation_failed');
    }

    public static function invalidQueries(): array
    {
        return array_map(fn ($query) => [$query], ['page=0', 'page=10001', 'per_page=101', 'per_page=-1', 'page[]=1', 'available=yes', 'available[]=1', 'status=draft', 'include=changes', 'sort=id', 'per_page=2.5']);
    }

    public function test_conditional_reads_revalidate_every_catalog_command_and_preserve_history(): void
    {
        $this->seed(RoleSeeder::class);
        $actor = User::factory()->create();
        $actor->assignRole(UserRole::Administrator->value);
        $actor->givePermissionTo(array_column(ProductCapability::cases(), 'value'));
        $actions = app(CatalogActions::class);
        $product = $actions->create($actor, ['name' => 'Synthetic conditional fixture'], (string) Str::uuid());
        $listTag = $this->getJson('/api/v1/products')->headers->get('ETag');
        $actions->publish($actor, $product, (string) Str::uuid());
        $this->getJson('/api/v1/products', ['If-None-Match' => $listTag])->assertOk()->assertJsonCount(1, 'data');
        $uri = '/api/v1/products/'.$product->public_id;
        $commands = [
            fn () => $actions->update($actor, $product, ['summary' => 'Changed summary'], (string) Str::uuid()),
            fn () => $actions->revisePrice($actor, $product, ['amount_minor' => 12345, 'currency' => 'PHP'], (string) Str::uuid()),
            fn () => $actions->reviseTerms($actor, $product, ['scope' => 'Synthetic scope'], (string) Str::uuid()),
            fn () => $actions->setAvailability($actor, $product, true, (string) Str::uuid()),
            fn () => $actions->setAvailability($actor, $product, false, (string) Str::uuid()),
        ];
        foreach ($commands as $command) {
            $response = $this->getJson($uri)->assertOk();
            $tag = $response->headers->get('ETag');
            $this->assertNotEmpty($tag);
            $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
            $this->getJson($uri, ['If-None-Match' => $tag])->assertStatus(304)->assertContent('');
            $command();
            $changed = $this->getJson($uri, ['If-None-Match' => $tag])->assertOk();
            $this->assertNotSame($tag, $changed->headers->get('ETag'));
        }
        $tag = $this->getJson($uri)->headers->get('ETag');
        DB::beginTransaction();
        $actions->update($actor, $product, ['summary' => 'Rolled back'], (string) Str::uuid());
        DB::rollBack();
        $this->getJson($uri, ['If-None-Match' => $tag])->assertStatus(304);
        $actions->unpublish($actor, $product, (string) Str::uuid());
        $this->getJson($uri, ['If-None-Match' => $tag])->assertNotFound()->assertHeader('Cache-Control', 'no-store, private');
        $actions->publish($actor, $product, (string) Str::uuid());
        $actions->archive($actor, $product, (string) Str::uuid());
        $this->getJson($uri, ['If-None-Match' => '*'])->assertNotFound();
        $actions->restore($actor, $product, (string) Str::uuid());
        $this->getJson($uri)->assertNotFound();
        $this->assertDatabaseCount('product_prices', 1);
        $this->assertDatabaseCount('product_terms', 1);
    }

    public function test_public_routes_are_read_only_and_share_a_dedicated_ip_limiter(): void
    {
        $product = $this->offer();
        foreach (['post', 'put', 'patch', 'delete'] as $method) {
            $this->json($method, '/api/v1/products/'.$product->public_id, ['status' => 'published'])->assertMethodNotAllowed();
        }
        $this->postJson('/api/v1/products')->assertMethodNotAllowed();
        foreach (['api.v1.products.index', 'api.v1.products.show'] as $name) {
            $middleware = Route::getRoutes()->getByName($name)->gatherMiddleware();
            $this->assertContains('throttle:api-catalog', $middleware);
            $this->assertNotContains('auth:sanctum', $middleware);
        }
        $limit = (RateLimiter::limiter('api-catalog'))(request());
        $this->assertSame(120, $limit->maxAttempts);
        for ($i = 0; $i < 120; $i++) {
            $response = $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.41'])->getJson($i % 2 ? '/api/v1/products' : '/api/v1/products/'.$product->public_id);
            $response->assertOk();
        }
        $this->getJson('/api/v1/products')->assertTooManyRequests()->assertHeader('Retry-After')->assertJsonPath('error.code', 'rate_limited');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.42'])->getJson('/api/v1/products')->assertOk();
    }

    public function test_latest_versions_determine_filtering_without_resurrecting_older_offers(): void
    {
        $first = $this->offer();
        $second = $this->offer();
        $first->prices()->create(['version' => 2, 'amount_minor' => 0, 'currency' => 'PHP']);
        $second->termsVersions()->create(['version' => 2, 'content' => []]);
        $this->getJson('/api/v1/products?available=1')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/products?available=0')->assertOk()->assertJsonCount(2, 'data');
        $this->getJson('/api/v1/products/'.$first->public_id)->assertOk()
            ->assertJsonPath('data.price.version', 2)->assertJsonPath('data.price.amount_minor', '0')->assertJsonPath('data.is_available', false);
        $this->getJson('/api/v1/products/'.$second->public_id)->assertOk()
            ->assertJsonPath('data.terms.version', 2)->assertJsonPath('data.is_available', false);
    }

    public function test_the_documented_synthetic_fixture_matches_the_live_resource_and_openapi_examples(): void
    {
        $fixture = json_decode(file_get_contents(base_path('tests/Fixtures/public-product.json')), true, flags: JSON_THROW_ON_ERROR);
        $product = $this->offer([
            'public_id' => $fixture['public_id'], 'slug' => $fixture['slug'], 'name' => $fixture['name'],
            'summary' => $fixture['summary'], 'description' => $fixture['description'], 'media' => $fixture['media'],
        ], PHP_INT_MAX);
        $this->getJson('/api/v1/products/'.$product->public_id)->assertOk()->assertJsonPath('data', $fixture);
        $contract = json_decode(file_get_contents(base_path('docs/api/openapi.json')), true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(array_keys($fixture), $contract['components']['schemas']['Product']['required']);
        foreach (['/api/v1/products', '/api/v1/products/{public_id}'] as $path) {
            $operation = $contract['paths'][$path]['get'];
            $this->assertSame([], $operation['security']);
            $this->assertArrayHasKey('304', $operation['responses']);
            $example = $operation['responses']['200']['content']['application/json']['example'];
            $this->assertSame($fixture, $path === '/api/v1/products' ? $example['data'][0] : $example['data']);
        }
    }

    public function test_list_and_head_revalidation_reflect_revisions_and_visibility(): void
    {
        $product = $this->offer();
        $tag = $this->getJson('/api/v1/products')->headers->get('ETag');
        $this->getJson('/api/v1/products', ['If-None-Match' => 'W/'.$tag])->assertStatus(304);
        $this->head('/api/v1/products', ['If-None-Match' => $tag])->assertStatus(304);
        $product->prices()->create(['version' => 2, 'amount_minor' => 55500, 'currency' => 'PHP']);
        $this->getJson('/api/v1/products', ['If-None-Match' => $tag])->assertOk()->assertJsonPath('data.0.price.amount_minor', '55500');
        $tag = $this->getJson('/api/v1/products')->headers->get('ETag');
        $product->status = ProductStatus::Draft;
        $product->is_available = false;
        $product->save();
        $this->getJson('/api/v1/products', ['If-None-Match' => $tag])->assertOk()->assertJsonCount(0, 'data');
    }

    private function offer(array $attributes = [], int $amount = 12500): Product
    {
        $product = Product::factory()->published()->create($attributes);
        $product->prices()->create(['version' => 1, 'amount_minor' => $amount, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();

        return $product;
    }
}
