<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class OrderTest extends TestCase
{
    use RefreshDatabase;

    public function test_an_order_belongs_to_its_customer(): void
    {
        $customer = User::factory()->create();
        $order = Order::factory()->for($customer)->create();

        $this->assertTrue($order->user->is($customer));
        $this->assertTrue($customer->orders->first()->is($order));
    }

    public function test_order_status_is_a_backed_enum(): void
    {
        $order = Order::factory()->create(['status' => OrderStatus::Delivered]);

        $this->assertSame(OrderStatus::Delivered, $order->fresh()->status);
    }

    public function test_order_items_carry_product_quantity_and_unit_price(): void
    {
        $order = Order::factory()->create();
        $product = Product::factory()->create(['price_cents' => 1250]);
        $item = OrderItem::factory()->for($order)->for($product)->create([
            'quantity' => 3,
            'unit_price_cents' => 1250,
        ]);

        $this->assertTrue($order->items->first()->is($item));
        $this->assertTrue($item->product->is($product));
        $this->assertSame(3750, $item->quantity * $item->unit_price_cents);
    }

    public function test_order_number_is_unique(): void
    {
        Order::factory()->create(['number' => 'ORD-1001']);

        $this->expectException(QueryException::class);
        Order::factory()->create(['number' => 'ORD-1001']);
    }
}
