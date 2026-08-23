<?php

declare(strict_types=1);

namespace Tests\Feature\Capabilities\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\User;
use App\Verdict\OrderSearchScope;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\ActionProposal;
use Fissible\Verdict\Testing\CapabilitySecurityTestKit;
use Fissible\Verdict\VerdictManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The set-returning pattern (verdict#251): the target is a scope bound to the
 * authenticated customer, resolved from the trusted context alone, and the
 * model's filter is applied inside it. A hostile filter that matches another
 * customer's order is a *filtered permit* — the capability executes, the
 * permit row records target_source=context, and the foreign order is absent
 * from the results. The evidence of safety is result content, not a denial.
 */
final class SearchCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_capability_is_discovered_and_registered(): void
    {
        $capability = app(VerdictManager::class)->registeredCapability('orders.search');

        $this->assertSame('orders.search', $capability->name);
    }

    public function test_a_hostile_filter_is_a_filtered_permit(): void
    {
        $alice = User::factory()->create();
        $bruno = User::factory()->create();
        $lamp = Product::factory()->create(['name' => 'Aurora Desk Lamp']);
        $this->orderWith($alice, 'ORD-1002', $lamp);
        $this->orderWith($bruno, 'ORD-2001', $lamp);

        $result = app(VerdictManager::class)->runBound($this->searchEnvelope($alice, ['product' => 'Desk Lamp']));

        $this->assertTrue($result->executed);
        $rows = json_decode((string) $result->output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame(['ORD-1002'], array_column($rows, 'number'), 'Owned row present, foreign row absent — by identity.');
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.search',
            'disposition' => 'permit',
            'target_source' => 'context',
        ]);
    }

    public function test_filters_only_narrow_and_wildcards_are_literal(): void
    {
        $alice = User::factory()->create();
        $lamp = Product::factory()->create(['name' => 'Aurora Desk Lamp']);
        $mug = Product::factory()->create(['name' => 'Insulated Travel Mug']);
        $this->orderWith($alice, 'ORD-1002', $lamp, OrderStatus::Shipped);
        $this->orderWith($alice, 'ORD-1003', $mug, OrderStatus::Paid);

        $search = fn (array $filters): array => array_column(
            json_decode((string) app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters))->output, true, flags: JSON_THROW_ON_ERROR),
            'number',
        );

        $this->assertEqualsCanonicalizing(['ORD-1002', 'ORD-1003'], $search([]));
        $this->assertSame(['ORD-1003'], $search(['status' => 'PAID']));
        $this->assertSame(['ORD-1002'], $search(['product' => 'lamp']));
        $this->assertSame([], $search(['status' => 'shipped', 'product' => 'Mug']));
        $this->assertSame([], $search(['status' => 'cancelled']), 'A valid status no order has matches nothing.');
        $this->assertSame([], $search(['status' => 'returned']), 'An unrecognised status is a zero-result filter, not a dropped one.');
    }

    public function test_wildcards_in_the_product_term_match_literally(): void
    {
        $alice = User::factory()->create();
        $plain = Product::factory()->create(['name' => 'Aurora Desk Lamp']);
        $odd = Product::factory()->create(['name' => '100% Wool_Beanie \\ Winter!']);
        $this->orderWith($alice, 'ORD-1002', $plain);
        $this->orderWith($alice, 'ORD-1004', $odd);

        $search = fn (string $product): array => array_column(
            json_decode((string) app(VerdictManager::class)->runBound($this->searchEnvelope($alice, ['product' => $product]))->output, true, flags: JSON_THROW_ON_ERROR),
            'number',
        );

        // A bare wildcard would otherwise match every order; literally, it
        // matches only the product that actually contains the character.
        $this->assertSame(['ORD-1004'], $search('%'));
        $this->assertSame(['ORD-1004'], $search('_'));
        $this->assertSame([], $search('Desk_Lamp'), '_ is not a single-character wildcard.');
        $this->assertSame(['ORD-1004'], $search('100%'));
        $this->assertSame(['ORD-1004'], $search('Wool_Beanie'));
        $this->assertSame(['ORD-1004'], $search('\\'));
        $this->assertSame(['ORD-1004'], $search('!'), 'The escape character itself is searchable.');
    }

    public function test_the_scope_is_unresolvable_without_an_authenticated_customer(): void
    {
        $result = app(VerdictManager::class)->runBound(ActionEnvelope::wrap(
            new ActionProposal('orders.search', []),
            new ActionContext(actor: null),
        ));

        $this->assertFalse($result->executed);
        $this->assertDatabaseHas('verdict_evidence', ['capability' => 'orders.search', 'disposition' => 'deny']);
    }

    public function test_the_executor_acts_on_a_refreshed_scope(): void
    {
        $alice = User::factory()->create();

        CapabilitySecurityTestKit::for(app(VerdictManager::class), 'orders.search')
            ->assertRefreshedTargetSubstitution(
                $this->searchEnvelope($alice, []),
                fn (): bool => DB::table('verdict_evidence')->where('disposition', 'permit')->exists(),
            );

        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.search',
            'target_strategy' => 'refresh',
        ]);
    }

    public function test_the_policy_layer_denies_a_foreign_scope_even_if_reached(): void
    {
        // Defense in depth: the context-resolved resolver can only build the
        // actor's own scope, so this is unreachable through the capability.
        $alice = User::factory()->create();
        $bruno = User::factory()->create();
        $scope = OrderSearchScope::forContext(new ActionContext(actor: $alice));

        $this->assertTrue(Gate::forUser($alice)->inspect('search', $scope)->allowed());
        $this->assertTrue(Gate::forUser($bruno)->inspect('search', $scope)->denied());
    }

    private function orderWith(User $customer, string $number, Product $product, OrderStatus $status = OrderStatus::Shipped): Order
    {
        $order = Order::factory()->for($customer)->create(['number' => $number, 'status' => $status]);
        OrderItem::factory()->for($order)->for($product)->create();

        return $order;
    }

    /** @param array<string, mixed> $filters */
    private function searchEnvelope(User $actor, array $filters): ActionEnvelope
    {
        return ActionEnvelope::wrap(
            new ActionProposal('orders.search', $filters),
            new ActionContext(actor: $actor),
        );
    }
}
