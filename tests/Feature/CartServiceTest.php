<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Catalog\CatalogActions;
use App\Actions\Catalog\CategoryActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartChange;
use App\Models\CartItem;
use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CartServiceTest extends TestCase
{
    use RefreshDatabase;

    private CartActions $actions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        $this->actions = app(CartActions::class);
    }

    public function test_guest_cart_is_private_hashed_retry_safe_and_has_no_expiry(): void
    {
        $secret = bin2hex(random_bytes(32));
        $guest = CartPrincipal::guest($secret);
        $operation = (string) Str::uuid();
        $cart = $this->actions->create($guest, $operation);
        $this->assertSame($cart, $this->actions->create($guest, strtoupper($operation)));
        $stored = Cart::firstOrFail();
        $this->assertSame(hash('sha256', $secret), $stored->credential_hash);
        $this->assertStringNotContainsString($secret, $stored->toJson());
        $this->assertArrayNotHasKey('credential_hash', $stored->toArray());
        $this->assertFalse(Schema::hasColumn('carts', 'expires_at'));
        $this->assertFalse(Schema::hasColumn('cart_items', 'expires_at'));
        $product = $this->offer();
        $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->travel(730)->days();
        $read = $this->actions->read($guest, $cart['public_id']);
        $this->assertCount(1, $read['lines']);
        $this->assertTrue($read['selection_valid']);
        $this->assertSame('12500', $read['subtotal_minor']);
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_changes', 2);
        $this->travelBack();
    }

    public function test_wrong_guest_proof_and_other_customers_cannot_read_mutate_or_replay(): void
    {
        $owner = $this->customer();
        $principal = CartPrincipal::customer($owner);
        $cart = $this->actions->create($principal, (string) Str::uuid());
        $product = $this->offer();
        $operation = (string) Str::uuid();
        $this->actions->add($principal, $cart['public_id'], $product->public_id, $operation);
        foreach ([CartPrincipal::guest(bin2hex(random_bytes(32))), CartPrincipal::customer($this->customer())] as $wrong) {
            foreach (['read', 'add', 'remove', 'confirm'] as $command) {
                try {
                    match ($command) {
                        'read' => $this->actions->read($wrong, $cart['public_id']),
                        'add' => $this->actions->add($wrong, $cart['public_id'], $product->public_id, $operation),
                        'remove' => $this->actions->remove($wrong, $cart['public_id'], $product->public_id, (string) Str::uuid()),
                        'confirm' => $this->actions->confirm($wrong, $cart['public_id'], [], (string) Str::uuid()),
                    };
                    $this->fail('Foreign cart authority was accepted.');
                } catch (ModelNotFoundException) {
                    $this->addToAssertionCount(1);
                }
            }
        }
        $this->assertFalse(Gate::forUser($this->customer())->allows('view', [Cart::firstOrFail(), $principal]));
        $privileged = $this->customer();
        $privileged->assignRole(UserRole::SuperAdmin->value);
        try {
            $this->actions->read(CartPrincipal::customer($privileged), $cart['public_id']);
            $this->fail('Super-admin Gate override bypassed the scoped cart query.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('cart_changes', 2);
        $this->assertSame($owner->id, CartChange::latest('id')->firstOrFail()->actor_id);
    }

    public function test_guest_authority_is_bound_to_its_own_cart_and_creation_receipt(): void
    {
        $first = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $second = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $operation = (string) Str::uuid();
        $cart = $this->actions->create($first, $operation);
        try {
            $this->actions->read($second, $cart['public_id']);
            $this->fail('Wrong secret accepted.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        try {
            $this->actions->create($second, $operation);
            $this->fail('A receipt returned a different principal cart.');
        } catch (HttpException $exception) {
            $this->assertSame(404, $exception->getStatusCode());
        }
        $this->assertDatabaseCount('carts', 1);
    }

    public function test_staff_cannot_become_customer_cart_authority_and_revocation_is_checked(): void
    {
        foreach ([UserRole::Administrator, UserRole::SuperAdmin] as $role) {
            $staff = User::factory()->create();
            $staff->assignRole($role->value);
            try {
                CartPrincipal::customer($staff);
                $this->fail('Staff acquired customer authority.');
            } catch (HttpException $exception) {
                $this->assertSame(403, $exception->getStatusCode());
            }
        }
        $user = $this->customer();
        $principal = CartPrincipal::customer($user);
        $cart = $this->actions->create($principal, (string) Str::uuid());
        $user->removeRole(UserRole::Customer->value);
        $this->expectException(HttpException::class);
        $this->actions->read($principal, $cart['public_id']);
    }

    public function test_category_limit_preserves_existing_item_and_other_categories_can_coexist(): void
    {
        $category = ProductCategory::create(['name' => 'Restricted', 'limit_one_per_cart' => true]);
        $regular = ProductCategory::create(['name' => 'Regular']);
        $first = $this->offer($category);
        $other = $this->offer($category);
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $this->actions->add($guest, $cart['public_id'], $first->public_id, (string) Str::uuid());
        try {
            $this->actions->add($guest, $cart['public_id'], $other->public_id, (string) Str::uuid());
            $this->fail('Restricted category accepted a second product.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('product', $exception->errors());
        }
        $secondRestricted = ProductCategory::create(['name' => 'Another restricted category', 'limit_one_per_cart' => true]);
        foreach ([$this->offer($regular), $this->offer($regular), $this->offer(), $this->offer($secondRestricted)] as $product) {
            $read = $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        }
        $this->assertCount(5, $read['lines']);
        $this->assertTrue($read['selection_valid']);
        $this->assertSame('62500', $read['subtotal_minor']);
        $this->assertDatabaseHas('cart_items', ['product_id' => $first->id]);
        $this->assertDatabaseMissing('cart_items', ['product_id' => $other->id]);
        $this->actions->remove($guest, $cart['public_id'], $first->public_id, (string) Str::uuid());
        $read = $this->actions->add($guest, $cart['public_id'], $other->public_id, (string) Str::uuid());
        $this->assertCount(5, $read['lines']);
    }

    public function test_saved_cart_revalidates_later_category_changes_without_losing_items(): void
    {
        $category = ProductCategory::create(['name' => 'Initially regular']);
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $first = $this->offer($category);
        $second = $this->offer($category);
        foreach ([$first, $second] as $product) {
            $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        }
        $admin = $this->admin();
        app(CategoryActions::class)->update($admin, $category, ['limit_one_per_cart' => true], (string) Str::uuid());
        $read = $this->actions->read($guest, $cart['public_id']);
        $this->assertFalse($read['selection_valid']);
        $this->assertSame([$category->public_id], $read['category_conflicts']);
        $this->assertTrue($read['lines'][0]['category_conflict']);
        $this->assertCount(2, $read['lines']);
        try {
            $this->actions->confirm($guest, $cart['public_id'], $this->versions($read), (string) Str::uuid());
            $this->fail('Conflicting cart was confirmed.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $read = $this->actions->remove($guest, $cart['public_id'], $first->public_id, (string) Str::uuid());
        $this->assertTrue($read['selection_valid']);
    }

    public function test_price_and_terms_changes_require_explicit_current_version_confirmation(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $product = $this->offer();
        $read = $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $old = $this->versions($read);
        $admin = $this->admin();
        $catalog = app(CatalogActions::class);
        $catalog->revisePrice($admin, $product, ['amount_minor' => 15000, 'currency' => 'PHP'], (string) Str::uuid());
        $catalog->reviseTerms($admin, $product, ['scope' => 'Changed synthetic scope'], (string) Str::uuid());
        $read = $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->assertCount(1, $read['lines']);
        $this->assertSame(1, $read['lines'][0]['quoted_price_version']);
        $this->assertSame('15000', $read['subtotal_minor']);
        $this->assertTrue($read['requires_review']);
        $this->assertFalse($read['selection_valid']);
        try {
            $this->actions->confirm($guest, $cart['public_id'], $old, (string) Str::uuid());
            $this->fail('Old versions confirmed a newer offer.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $operation = (string) Str::uuid();
        $confirmed = $this->actions->confirm($guest, $cart['public_id'], $this->versions($read), $operation);
        $this->assertTrue($confirmed['selection_valid']);
        $this->assertFalse($confirmed['requires_review']);
        $this->assertSame(2, $confirmed['lines'][0]['quoted_price_version']);
        $catalog->revisePrice($admin, $product, ['amount_minor' => 16000, 'currency' => 'PHP'], (string) Str::uuid());
        $replayed = $this->actions->confirm($guest, $cart['public_id'], $this->versions($read), $operation);
        $this->assertTrue($replayed['requires_review']);
        $this->assertSame(2, $replayed['lines'][0]['quoted_price_version']);
        $this->assertSame('16000', $replayed['subtotal_minor']);
    }

    public function test_intervening_offer_change_rolls_back_the_entire_confirmation(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $first = $this->offer();
        $second = $this->offer();
        $catalog = app(CatalogActions::class);
        $admin = $this->admin();
        foreach ([$first, $second] as $product) {
            $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
            $catalog->revisePrice($admin, $product, ['amount_minor' => 13000, 'currency' => 'PHP'], (string) Str::uuid());
        }
        $reviewed = $this->versions($this->actions->read($guest, $cart['public_id']));
        $catalog->revisePrice($admin, $second, ['amount_minor' => 14000, 'currency' => 'PHP'], (string) Str::uuid());
        try {
            $this->actions->confirm($guest, $cart['public_id'], $reviewed, (string) Str::uuid());
            $this->fail('Intervening offer change was confirmed.');
        } catch (ValidationException) {
            $this->addToAssertionCount(1);
        }
        $read = $this->actions->read($guest, $cart['public_id']);
        $this->assertSame([1, 1], array_column($read['lines'], 'quoted_price_version'));
        $this->assertTrue($read['requires_review']);
        $this->assertDatabaseCount('cart_changes', 3);
    }

    #[DataProvider('unavailableStates')]
    public function test_unavailable_saved_items_are_retained_but_cannot_be_added_or_confirmed(string $transition): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $product = $this->offer();
        $read = $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $admin = $this->admin();
        match ($transition) {
            'unavailable' => app(CatalogActions::class)->setAvailability($admin, $product, false, (string) Str::uuid()),
            'draft' => app(CatalogActions::class)->unpublish($admin, $product, (string) Str::uuid()),
            'archived' => app(CatalogActions::class)->archive($admin, $product, (string) Str::uuid()),
        };
        $read = $this->actions->read($guest, $cart['public_id']);
        $this->assertCount(1, $read['lines']);
        $this->assertFalse($read['lines'][0]['available']);
        $this->assertFalse($read['selection_valid']);
        foreach (['add', 'confirm'] as $command) {
            try {
                $command === 'add'
                    ? $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid())
                    : $this->actions->confirm($guest, $cart['public_id'], $this->versions($read), (string) Str::uuid());
                $this->fail('Unavailable service was accepted.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $removed = $this->actions->remove($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->assertSame([], $removed['lines']);
        $this->assertFalse($removed['selection_valid']);
    }

    public static function unavailableStates(): array
    {
        return [['unavailable'], ['draft'], ['archived']];
    }

    public function test_duplicate_commands_quantity_one_and_conflicting_operation_reuse(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $product = $this->offer();
        $operation = (string) Str::uuid();
        $first = $this->actions->add($guest, $cart['public_id'], $product->public_id, $operation);
        $this->assertSame($first['revision'], $this->actions->add($guest, $cart['public_id'], $product->public_id, $operation)['revision']);
        $this->assertSame(1, $first['lines'][0]['quantity']);
        $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $this->assertDatabaseCount('cart_items', 1);
        try {
            $this->actions->remove($guest, $cart['public_id'], $product->public_id, $operation);
            $this->fail('Conflicting command reused a receipt.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('operation_id', $exception->errors());
        }
        $remove = (string) Str::uuid();
        $this->actions->remove($guest, $cart['public_id'], $product->public_id, $remove);
        $this->actions->remove($guest, $cart['public_id'], $product->public_id, $remove);
        $this->assertSame([], $this->actions->add($guest, $cart['public_id'], $product->public_id, $operation)['lines']);
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('cart_changes', 4);
    }

    public function test_invalid_unconfigured_and_empty_confirmation_inputs_do_not_commit(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $unconfigured = Product::factory()->published()->create();
        foreach ([
            fn () => CartPrincipal::guest('guessable'),
            fn () => $this->actions->create($guest, 'invalid-uuid'),
            fn () => $this->actions->add($guest, $cart['public_id'], $unconfigured->public_id, (string) Str::uuid()),
            fn () => $this->actions->add($guest, $cart['public_id'], '../../internal', (string) Str::uuid()),
            fn () => $this->actions->confirm($guest, $cart['public_id'], [], (string) Str::uuid()),
            fn () => $this->actions->confirm($guest, $cart['public_id'], [$unconfigured->public_id => ['price' => 1, 'terms' => 1, 'total' => 1]], (string) Str::uuid()),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Invalid cart input committed.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('cart_items', 0);
        $this->assertDatabaseCount('cart_changes', 1);
    }

    public function test_subtotals_are_exact_above_native_integer_range(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        foreach ([$this->offer(amount: PHP_INT_MAX), $this->offer(amount: PHP_INT_MAX)] as $product) {
            $read = $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        }
        $this->assertSame('18446744073709551614', $read['subtotal_minor']);
        $this->assertTrue($read['subtotal_complete']);
    }

    public function test_history_identity_and_offer_product_bindings_cannot_be_edited(): void
    {
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $this->actions->create($guest, (string) Str::uuid());
        $product = $this->offer();
        $other = $this->offer();
        $this->actions->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        foreach ([
            fn () => Cart::firstOrFail()->forceFill(['user_id' => $this->customer()->id])->save(),
            fn () => Cart::firstOrFail()->delete(),
            fn () => CartChange::firstOrFail()->forceFill(['action' => 'altered'])->save(),
            fn () => CartChange::firstOrFail()->delete(),
            fn () => CartItem::firstOrFail()->forceFill(['quoted_price_id' => $other->currentPrice->id])->save(),
            fn () => CartItem::firstOrFail()->forceFill(['product_id' => $other->id])->save(),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Protected state was modified.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->assertDatabaseCount('cart_items', 1);
        $this->assertDatabaseCount('cart_changes', 2);
    }

    private function offer(?ProductCategory $category = null, int $amount = 12500): Product
    {
        $product = Product::factory()->published()->create(['category_id' => $category?->id]);
        $product->prices()->create(['version' => 1, 'amount_minor' => $amount, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();

        return $product;
    }

    private function versions(array $snapshot): array
    {
        $result = [];
        foreach ($snapshot['lines'] as $line) {
            $result[$line['product']->public_id] = ['price' => $line['product']->currentPrice->version, 'terms' => $line['product']->currentTerms->version];
        }

        return $result;
    }

    private function customer(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::Customer->value);

        return $user;
    }

    private function admin(): User
    {
        $user = User::factory()->create();
        $user->assignRole(UserRole::SuperAdmin->value);

        return $user;
    }
}
