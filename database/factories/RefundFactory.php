<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Refund>
 */
final class RefundFactory extends Factory
{
    public function definition(): array
    {
        return [
            'order_id' => Order::factory(),
            'amount_cents' => fake()->numberBetween(500, 50000),
            'reason' => fake()->sentence(),
            'issued_at' => fake()->dateTimeBetween('-1 month'),
        ];
    }
}
