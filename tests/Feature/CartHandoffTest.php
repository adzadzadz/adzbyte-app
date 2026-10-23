<?php

namespace Tests\Feature;

use App\Actions\Carts\CartActions;
use App\Actions\Carts\CartHandoffActions;
use App\Enums\UserRole;
use App\Models\Cart;
use App\Models\CartChange;
use App\Models\CartHandoff;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use App\Services\Carts\CartSession;
use App\Services\Carts\StorefrontCredential;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\PersonalAccessToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class CartHandoffTest extends TestCase
{
    use RefreshDatabase;

    private CartHandoffActions $handoffs;

    private CartPrincipal $principal;

    private User $service;

    private PersonalAccessToken $issuer;

    private string $cart;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(RoleSeeder::class);
        config(['app.url' => 'https://app.example.test']);
        $this->travelTo(now()->startOfSecond());
        $this->handoffs = app(CartHandoffActions::class);
        $this->principal = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $this->service = User::factory()->create();
        $this->issuer = $this->service->createToken('storefront:handoff-test', StorefrontCredential::abilities())->accessToken;
        $this->cart = app(CartActions::class)->create($this->principal, (string) Str::uuid())['public_id'];
    }

    public function test_mint_is_bound_retry_safe_hashed_and_expires_in_sixty_seconds(): void
    {
        $operation = (string) Str::uuid();
        $link = $this->mint($operation);
        $this->assertSame($link, $this->mint(strtoupper($operation)));
        $this->assertStringStartsWith('https://app.example.test/cart/handoff#', $link['url']);
        $secret = $this->secret($link);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $secret);
        $handoff = CartHandoff::firstOrFail();
        $this->assertSame(hash('sha256', $secret), $handoff->token_hash);
        $this->assertSame(60, (int) now()->diffInSeconds($handoff->expires_at));
        $this->assertNull($handoff->consumed_at);
        $this->assertStringNotContainsString($secret, $handoff->toJson());
        $this->assertStringNotContainsString($secret, CartChange::latest('id')->firstOrFail()->toJson());
        $this->assertDatabaseCount('cart_handoffs', 1);
        $this->assertDatabaseCount('cart_changes', 2);
        $this->assertSame(0, Cart::firstOrFail()->revision);
    }

    public function test_atomic_consume_opens_a_rotated_server_session_and_cannot_replay(): void
    {
        $link = $this->mint();
        $this->travel(59)->seconds();
        $grant = $this->handoffs->consume($this->secret($link));
        $session = Session::driver();
        $session->start();
        $session->put('unrelated', 'retained');
        $oldId = $session->getId();
        $oldCsrf = $session->token();
        $context = app(CartSession::class);
        $context->open($session, $grant);
        $this->assertNotSame($oldId, $session->getId());
        $this->assertNotSame($oldCsrf, $session->token());
        $this->assertSame('retained', $session->get('unrelated'));
        $this->assertSame($this->cart, $context->reference($session));
        $this->assertSame($this->cart, app(CartActions::class)->read($context->principal($session), $context->reference($session))['public_id']);
        $this->assertStringNotContainsString($grant->authorityHash(), json_encode($grant));
        $this->assertNotNull(CartHandoff::firstOrFail()->consumed_at);
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
    }

    public function test_expired_link_does_not_expire_items_and_new_operation_gets_a_fresh_link(): void
    {
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => 12500, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic scope']]);
        $product->is_available = true;
        $product->save();
        app(CartActions::class)->add($this->principal, $this->cart, $product->public_id, (string) Str::uuid());
        $operation = (string) Str::uuid();
        $link = $this->mint($operation);
        $this->travel(60)->seconds();
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
        $this->expectStatus(409, fn () => $this->mint($operation));
        $this->assertCount(1, app(CartActions::class)->read($this->principal, $this->cart)['lines']);
        $fresh = $this->mint();
        $this->assertNotSame($link['url'], $fresh['url']);
        $this->assertSame($this->cart, $this->handoffs->consume($this->secret($fresh))->reference);
    }

    public function test_unknown_tampered_and_nonce_values_do_not_consume_a_transfer(): void
    {
        $link = $this->mint();
        foreach (['invalid', bin2hex(random_bytes(32)), CartHandoff::firstOrFail()->public_id, substr($this->secret($link), 0, 63).'z'] as $invalid) {
            $this->expectStatus(410, fn () => $this->handoffs->consume($invalid));
        }
        $this->assertNull(CartHandoff::firstOrFail()->consumed_at);
    }

    #[DataProvider('revocations')]
    public function test_revoked_expired_or_changed_issuer_cannot_transfer_a_cart(string $change): void
    {
        $link = $this->mint();
        match ($change) {
            'delete' => $this->issuer->delete(),
            'expiry' => $this->issuer->forceFill(['expires_at' => now()->subSecond()])->save(),
            'role' => $this->service->assignRole(UserRole::Customer->value),
            'ability' => $this->issuer->forceFill(['abilities' => ['cart:read']])->save(),
            'provider' => $this->issuer->forceFill(['tokenable_type' => 'OtherIdentity'])->save(),
        };
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
        $this->assertNull(CartHandoff::firstOrFail()->consumed_at);
    }

    public static function revocations(): array
    {
        return [['delete'], ['expiry'], ['role'], ['ability'], ['provider']];
    }

    public function test_application_key_rotation_invalidates_existing_transfer_and_mint_retry(): void
    {
        $operation = (string) Str::uuid();
        $link = $this->mint($operation);
        config(['app.key' => 'base64:'.base64_encode(random_bytes(32))]);
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
        $this->expectStatus(409, fn () => $this->mint($operation));
        $fresh = $this->mint();
        $this->assertSame($this->cart, $this->handoffs->consume($this->secret($fresh))->reference);
    }

    public function test_changed_cart_authority_invalidates_old_transfer_and_guest_access(): void
    {
        $operation = (string) Str::uuid();
        $link = $this->mint($operation);
        $newProof = bin2hex(random_bytes(32));
        DB::table('carts')->where('public_id', $this->cart)->update(['credential_hash' => hash('sha256', $newProof)]);
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
        $principal = CartPrincipal::guest($newProof);
        $this->expectStatus(409, fn () => $this->handoffs->mint($principal, $this->cart, $this->issuer, $operation));
        $fresh = $this->handoffs->mint($principal, $this->cart, $this->issuer, (string) Str::uuid());
        $this->assertSame($this->cart, $this->handoffs->consume($this->secret($fresh))->reference);
        $this->expectException(ModelNotFoundException::class);
        app(CartActions::class)->read($this->principal, $this->cart);
    }

    public function test_wrong_cart_authority_or_insufficient_issuer_cannot_mint(): void
    {
        try {
            $this->handoffs->mint(CartPrincipal::guest(bin2hex(random_bytes(32))), $this->cart, $this->issuer, (string) Str::uuid());
            $this->fail('Wrong cart proof minted a link.');
        } catch (ModelNotFoundException) {
            $this->addToAssertionCount(1);
        }
        $limited = $this->service->createToken('limited', ['cart:read'])->accessToken;
        $this->expectStatus(403, fn () => $this->handoffs->mint($this->principal, $this->cart, $limited, (string) Str::uuid()));
        $this->assertDatabaseCount('cart_handoffs', 0);
    }

    public function test_pruning_expired_transfers_retains_carts_and_prevents_old_uuid_reissuance(): void
    {
        $operation = (string) Str::uuid();
        $link = $this->mint($operation);
        $this->travel(25)->hours();
        $this->artisan('commerce:prune-cart-handoffs')->assertSuccessful();
        $this->assertDatabaseCount('cart_handoffs', 0);
        $this->assertDatabaseCount('carts', 1);
        $this->assertDatabaseCount('cart_changes', 2);
        $this->expectStatus(410, fn () => $this->handoffs->consume($this->secret($link)));
        $this->expectStatus(409, fn () => $this->mint($operation));
        $fresh = $this->mint();
        $this->assertNotSame($link['url'], $fresh['url']);
        $this->assertSame($this->cart, $this->handoffs->consume($this->secret($fresh))->reference);
    }

    public function test_issuance_fields_and_consumed_timestamp_cannot_be_rewritten(): void
    {
        $link = $this->mint();
        foreach ([
            fn () => CartHandoff::firstOrFail()->forceFill(['expires_at' => now()->addYear()])->save(),
            fn () => CartHandoff::firstOrFail()->delete(),
        ] as $invalid) {
            try {
                $invalid();
                $this->fail('Transfer fields were rewritten.');
            } catch (ValidationException) {
                $this->addToAssertionCount(1);
            }
        }
        $this->handoffs->consume($this->secret($link));
        $this->expectException(ValidationException::class);
        CartHandoff::firstOrFail()->forceFill(['consumed_at' => null])->save();
    }

    private function mint(?string $operation = null): array
    {
        return $this->handoffs->mint($this->principal, $this->cart, $this->issuer, $operation ?? (string) Str::uuid());
    }

    private function secret(array $link): string
    {
        return parse_url($link['url'], PHP_URL_FRAGMENT);
    }

    private function expectStatus(int $status, callable $operation): void
    {
        try {
            $operation();
            $this->fail('Expected denied transfer operation.');
        } catch (HttpException $exception) {
            $this->assertSame($status, $exception->getStatusCode());
        }
    }
}
