<?php

declare(strict_types=1);

namespace Tests\Feature\Domain;

use App\Models\Order;
use App\Models\Refund;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class RefundTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_refund_belongs_to_its_order(): void
    {
        $order = Order::factory()->create();
        $refund = Refund::factory()->for($order)->create([
            'amount_cents' => 4200,
            'reason' => 'Damaged in transit',
        ]);

        $this->assertTrue($refund->order->is($order));
        $this->assertTrue($order->refunds->first()->is($refund));
        $this->assertSame(4200, $refund->amount_cents);
    }

    public function test_issued_at_is_a_datetime(): void
    {
        $refund = Refund::factory()->create();

        $this->assertInstanceOf(Carbon::class, $refund->fresh()->issued_at);
    }
}
