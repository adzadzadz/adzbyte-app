<?php

namespace Database\Factories;

use App\Models\Product;
use App\Models\ProductTerms;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<ProductTerms> */
class ProductTermsFactory extends Factory
{
    public function definition(): array
    {
        return [
            'product_id' => Product::factory(),
            'version' => 1,
            'content' => ['scope' => 'Synthetic test scope; not a real service commitment.'],
        ];
    }
}
