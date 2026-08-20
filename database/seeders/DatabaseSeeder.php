<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Synthetic, reversible, deterministic demo data — the stage every README
 * walkthrough and replay fixture plays on. Fixed identifiers (emails, SKUs,
 * order numbers) so recorded model proposals can reference them; idempotent
 * (`composer run setup` re-runs it on every invocation); restored in full by
 * `php artisan migrate:fresh --seed`. Refunds are never seeded: they exist
 * only as the outcome of the confirmation-gated capability.
 */
final class DatabaseSeeder extends Seeder
{
    private const PRODUCTS = [
        'LAMP-001' => ['Aurora Desk Lamp', 4900],
        'NOTE-003' => ['Field Notebook (3-pack)', 1800],
        'POUR-010' => ['Ceramic Pour-Over Set', 6400],
        'BEAN-021' => ['Merino Beanie', 3200],
        'STND-014' => ['Walnut Phone Stand', 2700],
        'MUGT-007' => ['Insulated Travel Mug', 3500],
    ];

    /** order number => [customer email, status, placed_at, items as sku => quantity] */
    private const ORDERS = [
        'ORD-1001' => ['alice@example.com', OrderStatus::Delivered, '2026-07-02 10:15:00', ['POUR-010' => 1, 'NOTE-003' => 2]],
        'ORD-1002' => ['alice@example.com', OrderStatus::Shipped, '2026-08-05 16:40:00', ['LAMP-001' => 1]],
        'ORD-1003' => ['alice@example.com', OrderStatus::Paid, '2026-08-15 09:05:00', ['MUGT-007' => 3, 'BEAN-021' => 1]],
        'ORD-2001' => ['bruno@example.com', OrderStatus::Delivered, '2026-07-20 13:30:00', ['STND-014' => 1, 'LAMP-001' => 1]],
        'ORD-2002' => ['bruno@example.com', OrderStatus::Paid, '2026-08-12 18:55:00', ['BEAN-021' => 2]],
    ];

    public function run(): void
    {
        $customers = collect([
            'alice@example.com' => 'Alice Storey',
            'bruno@example.com' => 'Bruno Marchetti',
        ])->map(fn (string $name, string $email) => User::updateOrCreate(
            ['email' => $email],
            ['name' => $name, 'password' => 'password'],
        ));

        $products = collect(self::PRODUCTS)->map(fn (array $spec, string $sku) => Product::updateOrCreate(
            ['sku' => $sku],
            ['name' => $spec[0], 'price_cents' => $spec[1]],
        ));

        foreach (self::ORDERS as $number => [$email, $status, $placedAt, $items]) {
            $total = collect($items)
                ->map(fn (int $quantity, string $sku) => $quantity * $products[$sku]->price_cents)
                ->sum();

            $order = Order::updateOrCreate(
                ['number' => $number],
                [
                    'user_id' => $customers[$email]->id,
                    'status' => $status,
                    'total_cents' => $total,
                    'placed_at' => $placedAt,
                ],
            );

            foreach ($items as $sku => $quantity) {
                $order->items()->updateOrCreate(
                    ['product_id' => $products[$sku]->id],
                    ['quantity' => $quantity, 'unit_price_cents' => $products[$sku]->price_cents],
                );
            }
        }
    }
}
