<?php

namespace Tests\Support;

use App\Actions\Carts\CartActions;
use App\Actions\Orders\CommercePolicyActions;
use App\Actions\Orders\OrderActions;
use App\Actions\Orders\OrderReviewActions;
use App\Enums\OrderCapability;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Carts\CartPrincipal;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Str;

trait CreatesPaymentOrder
{
    private User $customer;

    private User $admin;

    private Order $order;

    private function createPaymentOrder(int|string $amount = 12500, ?array $policy = null): void
    {
        $this->seed(RoleSeeder::class);
        $this->customer = User::factory()->create();
        $this->customer->assignRole('customer');
        $this->admin = User::factory()->create();
        $this->admin->assignRole('administrator');
        $this->admin->givePermissionTo(array_column(OrderCapability::cases(), 'value'));
        if ($policy !== null) {
            app(CommercePolicyActions::class)->publish($this->admin, $policy, (string) Str::uuid());
        }
        $product = Product::factory()->published()->create();
        $product->prices()->create(['version' => 1, 'amount_minor' => $amount, 'currency' => 'PHP']);
        $product->termsVersions()->create(['version' => 1, 'content' => ['scope' => 'Synthetic service scope']]);
        $product->is_available = true;
        $product->save();
        $carts = app(CartActions::class);
        $guest = CartPrincipal::guest(bin2hex(random_bytes(32)));
        $cart = $carts->create($guest, (string) Str::uuid());
        $carts->add($guest, $cart['public_id'], $product->public_id, (string) Str::uuid());
        $preview = $carts->mergePreview($this->customer, $guest, $cart['public_id']);
        $carts->attach($this->customer, $guest, $cart['public_id'], (string) Str::uuid(), $preview['fingerprint']);
        $this->order = app(OrderActions::class)->submit($this->customer, [
            'brief' => ['goal' => 'Synthetic goal', 'scope' => 'Synthetic requested scope'],
            'billing' => ['name' => 'Synthetic buyer', 'email' => 'buyer@example.test', 'country' => 'PH'],
            'accept_terms' => true, 'fingerprint' => app(OrderActions::class)->review($this->customer)['fingerprint'],
        ], (string) Str::uuid());
        $this->order = app(OrderReviewActions::class)->staff($this->admin, $this->order->public_id, 'approve', ['expected_revision' => 1], (string) Str::uuid());
    }
}
