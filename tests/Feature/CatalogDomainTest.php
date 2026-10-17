<?php

namespace Tests\Feature;

use App\Enums\ProductStatus;
use App\Models\Product;
use App\Models\ProductPrice;
use App\Models\ProductTerms;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CatalogDomainTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_catalog_starts_empty_and_seeding_does_not_invent_packages(): void
    {
        $this->seed(DatabaseSeeder::class);

        $this->assertSame(0, Product::query()->count());
        $this->assertSame(0, ProductPrice::query()->count());
        $this->assertSame(0, ProductTerms::query()->count());
        $this->assertSame([], Product::query()->published()->get()->all());
        $this->getJson('/api/v1/products')->assertNotFound();
    }

    public function test_an_incomplete_draft_needs_no_price_or_terms_and_content_cannot_set_lifecycle(): void
    {
        $product = Product::create([]);

        $this->assertTrue(Str::isUlid($product->public_id));
        $this->assertNull($product->name);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertFalse($product->is_available);
        $this->assertNull($product->currentPrice);
        $this->assertNull($product->currentTerms);

        $product->fill(['status' => 'published', 'is_available' => true, 'public_id' => (string) Str::ulid()]);
        $this->assertSame(ProductStatus::Draft, $product->status);
        $this->assertFalse($product->is_available);
    }

    public function test_package_content_can_change_without_replacing_its_identity(): void
    {
        $product = Product::factory()->create();
        $identity = $product->public_id;

        $product->update([
            'name' => 'Synthetic revised service',
            'slug' => 'synthetic-revised-service',
            'description' => 'Updated synthetic description.',
            'media' => [['path' => 'catalog/example.webp', 'alt' => 'Synthetic illustration']],
        ]);

        $this->assertSame($identity, $product->refresh()->public_id);
        $this->assertSame('Synthetic revised service', $product->name);
        $this->assertSame('catalog/example.webp', $product->media[0]['path']);
    }

    public function test_current_versions_are_scoped_to_the_product_and_history_survives_archival(): void
    {
        $product = Product::factory()->create();
        $newPrice = $product->prices()->create(['version' => 2, 'amount_minor' => 9999, 'currency' => 'PHP']);
        $newTerms = $product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'Synthetic revised']]);
        $oldPrice = $product->prices()->create(['version' => 1, 'amount_minor' => 12345, 'currency' => 'PHP']);
        $oldTerms = $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic original']]);
        ProductPrice::factory()->create(['version' => 9, 'amount_minor' => 50000]);
        ProductTerms::factory()->create(['version' => 9]);

        $this->assertTrue($product->currentPrice->is($newPrice));
        $this->assertTrue($product->currentTerms->is($newTerms));

        $product->forceFill(['status' => ProductStatus::Published, 'is_available' => true])->save();
        $this->assertSame([$product->id], Product::query()->published()->pluck('id')->all());
        $product->forceFill(['status' => ProductStatus::Archived, 'is_available' => false])->save();

        $this->assertSame(0, Product::query()->published()->count());
        $this->assertSame(12345, $oldPrice->refresh()->amount_minor);
        $this->assertSame(['scope' => 'Synthetic original'], $oldTerms->refresh()->content);
        $this->assertSame(2, $product->prices()->count());
        $this->assertSame(2, $product->termsVersions()->count());
    }

    public function test_unpriced_products_can_be_listed_but_cannot_be_made_available(): void
    {
        $product = Product::factory()->create();
        $product->forceFill(['status' => ProductStatus::Published])->save();
        $this->assertSame(1, Product::query()->published()->count());

        $this->expectException(ValidationException::class);
        $product->forceFill(['is_available' => true])->save();
    }

    public function test_zero_price_and_empty_terms_do_not_enable_an_offer(): void
    {
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 0, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => []]);

        $this->assertInvalidAvailability($product);
        $product->prices()->create(['version' => 2, 'amount_minor' => 1, 'currency' => 'PHP']);
        $this->assertInvalidAvailability($product);
        $product->termsVersions()->create(['version' => 2, 'content' => ['scope' => 'Synthetic']]);
        $product->forceFill(['is_available' => true])->save();

        $this->assertTrue($product->refresh()->is_available);
    }

    #[DataProvider('invalidTransitions')]
    public function test_lifecycle_and_identity_guards_reject_invalid_changes(array $attributes, string $exception = ValidationException::class): void
    {
        $product = Product::factory()->create();

        if (($attributes['status'] ?? null) === 'published' && ! array_key_exists('name', $attributes)) {
            $product->forceFill(['status' => ProductStatus::Archived])->save();
        }

        $this->expectException($exception);
        $product->forceFill($attributes)->save();
    }

    public static function invalidTransitions(): array
    {
        return [
            'identity replacement' => [['public_id' => '01ARZ3NDEKTSV4RRFFQ69G5FAV']],
            'unknown status' => [['status' => 'paid'], \ValueError::class],
            'draft availability' => [['is_available' => true]],
            'blank publication' => [['name' => '  ', 'status' => 'published']],
            'archive directly published' => [['status' => 'published']],
        ];
    }

    public function test_unpublishing_disables_availability_and_archives_restore_only_as_drafts(): void
    {
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 100, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic']]);
        $product->forceFill(['is_available' => true])->save();

        try {
            $product->forceFill(['status' => ProductStatus::Draft])->save();
            $this->fail('Unpublishing must also disable availability.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_available', $exception->errors());
        }

        $product->refresh()->forceFill(['status' => ProductStatus::Draft, 'is_available' => false])->save();
        $product->forceFill(['status' => ProductStatus::Archived])->save();
        $product->forceFill(['status' => ProductStatus::Draft])->save();
        $this->assertSame(ProductStatus::Draft, $product->refresh()->status);
        $this->assertFalse($product->is_available);
    }

    public function test_offer_configuration_rechecks_current_versions_even_when_relations_were_loaded(): void
    {
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 100, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic']]);
        $product->forceFill(['is_available' => true])->save();
        $product->load(['currentPrice', 'currentTerms']);
        $this->assertTrue($product->hasConfiguredOffer());

        $product->prices()->create(['version' => 2, 'amount_minor' => 0, 'currency' => 'PHP']);
        $this->assertFalse($product->hasConfiguredOffer());
        $product->prices()->create(['version' => 3, 'amount_minor' => 200, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 2, 'content' => []]);
        $this->assertFalse($product->hasConfiguredOffer());
    }

    public function test_large_integer_amounts_round_trip_exactly(): void
    {
        $price = ProductPrice::factory()->create(['amount_minor' => PHP_INT_MAX]);
        $this->assertSame(PHP_INT_MAX, $price->refresh()->amount_minor);
    }

    #[DataProvider('invalidPrices')]
    public function test_invalid_prices_are_rejected_without_lossy_integer_casting(array $attributes): void
    {
        $product = Product::factory()->create();
        $this->expectException(ValidationException::class);
        $product->prices()->create(array_replace(['version' => 1, 'amount_minor' => 100, 'currency' => 'PHP'], $attributes));
    }

    public static function invalidPrices(): array
    {
        return [
            'negative' => [['amount_minor' => -1]],
            'fraction' => [['amount_minor' => 1.5]],
            'fractional string' => [['amount_minor' => '1.5']],
            'boolean amount' => [['amount_minor' => true]],
            'float amount' => [['amount_minor' => 1.0]],
            'missing amount' => [['amount_minor' => null]],
            'nonnumeric' => [['amount_minor' => 'free']],
            'overflow' => [['amount_minor' => '9223372036854775808']],
            'foreign currency' => [['currency' => 'USD']],
            'missing currency' => [['currency' => null]],
            'zero revision' => [['version' => 0]],
            'fractional revision' => [['version' => 1.5]],
            'boolean revision' => [['version' => true]],
        ];
    }

    public function test_price_and_terms_versions_cannot_be_rewritten_or_deleted(): void
    {
        $price = ProductPrice::factory()->create();
        $terms = ProductTerms::factory()->create();

        foreach ([$price, $terms] as $version) {
            foreach (['update', 'delete'] as $operation) {
                try {
                    $operation === 'delete' ? $version->delete() : $version->forceFill(['version' => 2])->save();
                    $this->fail('Catalog versions must be append-only.');
                } catch (ValidationException $exception) {
                    $this->assertArrayHasKey('version', $exception->errors());
                    $version->refresh();
                }
            }
        }
    }

    public function test_product_model_deletion_is_rejected(): void
    {
        $product = Product::factory()->create();
        $this->expectException(ValidationException::class);
        $product->delete();
    }

    #[DataProvider('versionModels')]
    public function test_product_deletion_is_also_restricted_by_version_foreign_keys(string $model): void
    {
        $version = $model::factory()->create();
        $this->expectException(QueryException::class);
        Product::query()->whereKey($version->product_id)->delete();
    }

    #[DataProvider('versionModels')]
    public function test_revision_uniqueness_is_enforced_in_the_database(string $model): void
    {
        $version = $model::factory()->create();
        $this->expectException(QueryException::class);
        $model::factory()->create(['product_id' => $version->product_id, 'version' => $version->version]);
    }

    public static function versionModels(): array
    {
        return [[ProductPrice::class], [ProductTerms::class]];
    }

    public function test_terms_require_a_positive_revision_and_a_json_document(): void
    {
        foreach ([['version' => 0], ['version' => true], ['content' => 'not a document']] as $invalid) {
            try {
                ProductTerms::factory()->create($invalid);
                $this->fail('Invalid terms must not be persisted.');
            } catch (ValidationException $exception) {
                $this->assertNotEmpty($exception->errors());
            }
        }
        $this->assertSame(0, ProductTerms::query()->count());
    }

    public function test_slugs_are_unique_but_multiple_drafts_may_have_no_slug(): void
    {
        Product::create([]);
        Product::create([]);
        Product::factory()->create(['slug' => 'synthetic-service']);
        $this->expectException(QueryException::class);
        Product::factory()->create(['slug' => 'synthetic-service']);
    }

    #[DataProvider('versionModels')]
    public function test_orphan_versions_cannot_be_created(string $model): void
    {
        $this->expectException(QueryException::class);
        $model::factory()->create(['product_id' => 999999]);
    }

    public function test_public_product_identifiers_have_a_database_uniqueness_boundary(): void
    {
        $product = Product::factory()->create();
        $duplicate = Product::factory()->make();
        $duplicate->forceFill(['public_id' => $product->public_id]);

        $this->expectException(QueryException::class);
        $duplicate->save();
    }

    private function assertInvalidAvailability(Product $product): void
    {
        try {
            $product->forceFill(['is_available' => true])->save();
            $this->fail('Incomplete offers must remain unavailable.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('is_available', $exception->errors());
            $product->refresh();
        }
    }
}
