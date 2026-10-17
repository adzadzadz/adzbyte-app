<?php

namespace Database\Factories;

use App\Enums\ProductStatus;
use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Product> */
class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => 'Synthetic service '.fake()->unique()->uuid(),
            'slug' => fake()->unique()->slug(3),
            'summary' => 'Synthetic catalog fixture; not a real service offering.',
        ];
    }

    public function published(): static
    {
        return $this->state(fn (): array => ['status' => ProductStatus::Published]);
    }
}
