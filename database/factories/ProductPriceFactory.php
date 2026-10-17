<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductPrice;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductPrice> */
class ProductPriceFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => 1,
            'amount_minor' => fake()->numberBetween(1, 100000),
            'currency' => 'PHP',
        ];
    }
}
