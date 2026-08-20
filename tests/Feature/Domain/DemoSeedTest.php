<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Product;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The seed data is the stage the README walkthroughs and replay fixtures play on:
 * it must be synthetic, deterministic (fixed order numbers the fixtures can
 * reference), reversible (re-seeding restores it), and shaped for the demos —
 * one customer to authenticate as, one to be the cross-principal target.
 */
final class DemoSeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeds_the_two_demo_customers(): void
    {
        $this->seed();

        $this->assertNotNull(User::where('email', 'alice@example.com')->first());
        $this->assertNotNull(User::where('email', 'bruno@example.com')->first());
        $this->assertSame(2, User::count());
    }

    public function test_alice_owns_the_walkthrough_orders_and_bruno_the_cross_principal_ones(): void
    {
        $this->seed();

        $alice = User::where('email', 'alice@example.com')->firstOrFail();
        $bruno = User::where('email', 'bruno@example.com')->firstOrFail();

        $this->assertSame(
            ['ORD-1001', 'ORD-1002', 'ORD-1003'],
            $alice->orders()->orderBy('number')->pluck('number')->all(),
        );
        $this->assertSame(
            ['ORD-2001', 'ORD-2002'],
            $bruno->orders()->orderBy('number')->pluck('number')->all(),
        );

        // The refund walkthrough needs a delivered order on each side of the boundary.
        $this->assertSame(OrderStatus::Delivered, Order::where('number', 'ORD-1001')->firstOrFail()->status);
        $this->assertSame(OrderStatus::Delivered, Order::where('number', 'ORD-2001')->firstOrFail()->status);
    }

    public function test_every_order_total_equals_the_sum_of_its_items(): void
    {
        $this->seed();

        $orders = Order::with('items')->get();
        $this->assertNotEmpty($orders);

        foreach ($orders as $order) {
            $this->assertNotEmpty($order->items, "{$order->number} has no items");
            $this->assertSame(
                $order->items->sum(fn ($item) => $item->quantity * $item->unit_price_cents),
                $order->total_cents,
                "{$order->number} total does not match its items",
            );
        }
    }

    public function test_seeding_is_idempotent(): void
    {
        $this->seed();

        $counts = [User::count(), Product::count(), Order::count()];

        $this->seed();

        $this->assertSame($counts, [User::count(), Product::count(), Order::count()]);
    }

    public function test_no_refunds_are_seeded(): void
    {
        $this->seed();

        $this->assertSame(0, Refund::count());
    }
}
