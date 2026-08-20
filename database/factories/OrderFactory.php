<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
final class OrderFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'number' => fake()->unique()->bothify('ORD-####'),
            'status' => fake()->randomElement(OrderStatus::cases()),
            'total_cents' => fake()->numberBetween(500, 50000),
            'placed_at' => fake()->dateTimeBetween('-1 year'),
        ];
    }
}
