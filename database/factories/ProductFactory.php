<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Product;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Product>
 */
final class ProductFactory extends Factory
{
    public function definition(): array
    {
        return [
            'name' => fake()->words(3, true),
            'sku' => fake()->unique()->bothify('SKU-####??'),
            'price_cents' => fake()->numberBetween(500, 20000),
            'description' => fake()->sentence(),
        ];
    }
}
