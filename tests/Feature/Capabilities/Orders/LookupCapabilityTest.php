<?php

declare(strict_types=1);

namespace Tests\Feature\Capabilities\Orders;

use App\Models\Order;
use App\Models\User;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\ActionProposal;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Fissible\Verdict\Testing\CapabilitySecurityTestKit;
use Fissible\Verdict\VerdictManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

/**
 * The headline pattern (verdict#192): the proposed order_number is a filter
 * within the authenticated customer's own orders, never a global key. A
 * cross-principal number resolves to nothing, and an unresolvable target is
 * a recorded denial — the walkthrough's evidence row — not an empty result.
 */
final class LookupCapabilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_capability_is_discovered_and_registered(): void
    {
        $capability = app(VerdictManager::class)->registeredCapability('orders.lookup');

        $this->assertSame('orders.lookup', $capability->name);
    }

    public function test_a_customer_reads_their_own_order(): void
    {
        $alice = User::factory()->create();
        $order = Order::factory()->for($alice)->create(['number' => 'ORD-1001']);

        $result = app(VerdictManager::class)->runBound($this->lookupEnvelope($alice, 'ORD-1001'));

        $this->assertTrue($result->executed);
        $disclosure = json_decode((string) $result->output, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('ORD-1001', $disclosure['number']);
        $this->assertSame($order->status->value, $disclosure['status']);
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'disposition' => 'permit',
        ]);
    }

    public function test_a_cross_principal_read_is_denied_without_executing(): void
    {
        $alice = User::factory()->create();
        $bruno = User::factory()->create();
        Order::factory()->for($bruno)->create(['number' => 'ORD-2001']);

        $result = app(VerdictManager::class)->runBound($this->lookupEnvelope($alice, 'ORD-2001'));

        $this->assertFalse($result->executed);
        $this->assertNull($result->output);
        $this->assertSame(TargetNotResolvable::DECISION_REASON, $result->evaluation->decision->reason);
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'disposition' => 'deny',
        ]);
        $this->assertSame(0, DB::table('verdict_evidence')->where('disposition', 'permit')->count());
    }

    public function test_the_executor_acts_on_a_refreshed_target(): void
    {
        $alice = User::factory()->create();
        Order::factory()->for($alice)->create(['number' => 'ORD-1001']);

        CapabilitySecurityTestKit::for(app(VerdictManager::class), 'orders.lookup')
            ->assertRefreshedTargetSubstitution(
                $this->lookupEnvelope($alice, 'ORD-1001'),
                fn (): bool => DB::table('verdict_evidence')->where('disposition', 'permit')->exists(),
            );

        // The kit throws on failure without registering PHPUnit assertions; pin the
        // outcome it verified so the test is never reported risky.
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'target_strategy' => 'refresh',
        ]);
    }

    public function test_the_policy_layer_denies_a_non_owner_even_if_reached(): void
    {
        // Defense in depth: the scoped resolver makes this unreachable through the
        // capability, so the walkthrough never shows it — but the fail-closed policy
        // must hold on its own if a future resolver regression exposes it.
        $alice = User::factory()->create();
        $bruno = User::factory()->create();
        $order = Order::factory()->for($alice)->create();

        $this->assertTrue(Gate::forUser($alice)->inspect('view', $order)->allowed());
        $this->assertTrue(Gate::forUser($bruno)->inspect('view', $order)->denied());
    }

    private function lookupEnvelope(User $actor, string $orderNumber): ActionEnvelope
    {
        return ActionEnvelope::wrap(
            new ActionProposal('orders.lookup', ['order_number' => $orderNumber]),
            new ActionContext(actor: $actor),
        );
    }
}
