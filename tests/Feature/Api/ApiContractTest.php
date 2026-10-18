<?php

namespace Tests\Feature\Api;

use App\Actions\Orders\OrderReviewActions;
use App\Enums\UserRole;
use App\Http\Responses\ApiErrorResponse;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

class ApiContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_current_user_endpoint_requires_authentication(): void
    {
        $this->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Authentication is required.',
                    'details' => [],
                ],
            ]);
    }

    public function test_a_verified_customer_receives_only_their_versioned_identity_resource(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create([
            'name' => 'API Customer',
            'email' => 'api-customer@example.test',
        ]);
        $customer->assignRole(UserRole::Customer->value);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/me')
            ->assertOk()
            ->assertExactJson([
                'data' => [
                    'name' => 'API Customer',
                    'email' => 'api-customer@example.test',
                    'email_verified_at' => $customer->email_verified_at->utc()->toIso8601String(),
                    'created_at' => $customer->created_at->utc()->toIso8601String(),
                    'updated_at' => $customer->updated_at->utc()->toIso8601String(),
                ],
                'meta' => [
                    'api_version' => 'v1',
                ],
            ]);
    }

    public function test_unverified_customers_receive_a_stable_error(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->unverified()->create();
        $customer->assignRole(UserRole::Customer->value);

        Sanctum::actingAs($customer);

        $this->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'email_unverified',
                    'message' => 'Your email address must be verified.',
                    'details' => [],
                ],
            ]);
    }

    public function test_a_verified_customer_web_session_can_use_the_stateful_api(): void
    {
        $this->seed(RoleSeeder::class);

        $customer = User::factory()->create();
        $customer->assignRole(UserRole::Customer->value);

        $this->actingAs($customer)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', $customer->email);
    }

    public function test_administrators_without_the_customer_role_cannot_enter_the_customer_api(): void
    {
        $this->seed(RoleSeeder::class);

        $administrator = User::factory()->create();
        $administrator->assignRole(UserRole::Administrator->value);

        Sanctum::actingAs($administrator);

        $this->getJson('/api/v1/me')
            ->assertForbidden()
            ->assertJsonPath('error.code', 'forbidden');
    }

    public function test_unknown_api_routes_and_unsupported_methods_use_the_error_contract(): void
    {
        $this->getJson('/api/v1/not-found')
            ->assertNotFound()
            ->assertJsonPath('error.code', 'not_found');

        $this->postJson('/api/v1/me')
            ->assertMethodNotAllowed()
            ->assertJsonPath('error.code', 'method_not_allowed');
    }

    public function test_the_customer_route_uses_its_named_limiter_and_api_boundary_middleware(): void
    {
        $route = Route::getRoutes()->getByName('api.v1.me.show');

        $this->assertNotNull($route);
        $this->assertContains('auth:sanctum', $route->gatherMiddleware());
        $this->assertContains('verified', $route->gatherMiddleware());
        $this->assertContains('customer.api', $route->gatherMiddleware());
        $this->assertContains('throttle:api-customer', $route->gatherMiddleware());
        $this->assertIsCallable(RateLimiter::limiter('api-customer'));
        $this->assertIsCallable(RateLimiter::limiter('api-integration'));
        $this->assertIsCallable(RateLimiter::limiter('api-webhooks'));
    }

    public function test_only_documented_identity_catalog_cart_and_checkout_routes_are_exposed(): void
    {
        $versionedApiRoutes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (\Illuminate\Routing\Route $route): bool => str_starts_with($route->uri(), 'api/v1'))
            ->map(fn (\Illuminate\Routing\Route $route): ?string => $route->getName())
            ->values()
            ->all();

        $expected = [
            'api.v1.products.index', 'api.v1.products.show',
            'api.v1.storefront.carts.store', 'api.v1.storefront.carts.resolve',
            'api.v1.storefront.carts.show', 'api.v1.storefront.carts.add',
            'api.v1.storefront.carts.remove', 'api.v1.storefront.carts.confirm',
            'api.v1.storefront.carts.handoffs.store',
            'api.v1.cart-handoffs.consume', 'api.v1.cart.show', 'api.v1.cart.remove', 'api.v1.cart.confirm',
            'api.v1.me.show',
            'api.v1.webhooks.paymongo',
            'api.v1.checkout.show', 'api.v1.orders.store', 'api.v1.orders.index', 'api.v1.orders.show',
            'api.v1.orders.payment.show', 'api.v1.orders.payment.store',
            'api.v1.orders.review', 'api.v1.orders.reply', 'api.v1.orders.resubmit', 'api.v1.orders.accept_proposal',
            'api.v1.management.settings.show', 'api.v1.management.settings.update', 'api.v1.management.orders.index', 'api.v1.management.orders.show',
            ...array_map(fn ($action) => 'api.v1.management.orders.'.$action, array_keys(OrderReviewActions::STAFF)),
            'api.v1.registration.bootstrap', 'api.v1.registration.store',
            'api.v1.storefront.carts.retired', 'api.v1.cart.merge.show', 'api.v1.cart.attach',
        ];
        sort($expected);
        sort($versionedApiRoutes);
        $this->assertSame($expected, $versionedApiRoutes);
    }

    public function test_api_error_responses_preserve_validation_details_and_rate_limit_headers(): void
    {
        $validationResponse = ApiErrorResponse::fromException(
            response('', Response::HTTP_UNPROCESSABLE_ENTITY),
            ValidationException::withMessages([
                'name' => ['The name field is required.'],
            ]),
        );

        $this->assertSame('validation_failed', $validationResponse->getData(true)['error']['code']);
        $this->assertSame(
            ['The name field is required.'],
            $validationResponse->getData(true)['error']['details']['fields']['name'],
        );

        $rateLimitResponse = ApiErrorResponse::fromException(
            response('', Response::HTTP_TOO_MANY_REQUESTS, ['Retry-After' => '60']),
            new \RuntimeException,
        );

        $this->assertSame('rate_limited', $rateLimitResponse->getData(true)['error']['code']);
        $this->assertSame('60', $rateLimitResponse->headers->get('Retry-After'));
    }

    public function test_the_openapi_contract_matches_the_identity_surface(): void
    {
        $contract = json_decode(
            file_get_contents(base_path('docs/api/openapi.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame('3.1.0', $contract['openapi']);
        $this->assertArrayHasKey('/api/v1/me', $contract['paths']);
        $this->assertSame(
            '#/components/schemas/CurrentUserResponse',
            $contract['paths']['/api/v1/me']['get']['responses']['200']['content']['application/json']['schema']['$ref'],
        );
        $this->assertSame(
            ['name', 'email', 'email_verified_at', 'created_at', 'updated_at'],
            $contract['components']['schemas']['CurrentUser']['required'],
        );
    }
}
