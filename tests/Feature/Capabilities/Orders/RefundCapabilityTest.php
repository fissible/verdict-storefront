<?php

declare(strict_types=1);

namespace Tests\Feature\Capabilities\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\ActionProposal;
use Fissible\Verdict\Decisions\Disposition;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Fissible\Verdict\VerdictManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Tests\TestCase;

/**
 * The confirmation-gated mutation (verdict#230/#231 wiring, verified matrix
 * #233/#235): a refund proposal pauses for a human, an approved receipt is
 * consumed by exactly one execution, and the walkthrough's fourth demo —
 * pause → approval → exactly-once completion — is this capability's behavior
 * before any UI exists.
 */
final class RefundCapabilityTest extends TestCase
{
    // Not RefreshDatabase: its wrapping transaction trips Verdict's
    // UnsafeOuterTransaction guard on approval-receipt mutations (see
    // config/verdict.php, approvals.connection). Re-migrating is cheap
    // on the in-memory test database.
    use DatabaseMigrations;

    public function test_a_refund_proposal_pauses_for_confirmation(): void
    {
        $alice = $this->customerWithDeliveredOrder();

        $evaluation = app(VerdictManager::class)->evaluate($this->refundEnvelope($alice, 'ORD-1001'));

        $this->assertSame(Disposition::RequireConfirmation, $evaluation->decision->disposition);
        $this->assertSame(0, Refund::count());
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.refund',
            'disposition' => 'require_confirmation',
        ]);
    }

    public function test_an_unapproved_refund_never_executes(): void
    {
        $alice = $this->customerWithDeliveredOrder();

        $result = app(VerdictManager::class)->runBound($this->refundEnvelope($alice, 'ORD-1001'));

        $this->assertFalse($result->executed);
        $this->assertSame(0, Refund::count());
    }

    public function test_an_approved_refund_executes_exactly_once(): void
    {
        $alice = $this->customerWithDeliveredOrder();
        $envelope = $this->refundEnvelope($alice, 'ORD-1001');
        $verdict = app(VerdictManager::class);

        $evaluation = $verdict->evaluate($envelope);
        $verdict->approvals()->issue($evaluation);
        $challenge = $verdict->approvals()->challengeForToolCall('call-refund-1');
        $this->assertNotNull($challenge);

        $verdict->approvals()->approve($challenge->receiptId, $challenge->toolCallId, 'support:demo');

        // The resume carries the human's explicit per-call decision, the way the
        // laravel/ai approval middleware does in production — a receipt alone is
        // not enough (verified matrix, verdict#233/#235).
        $decisions = Decisions::from(['call-refund-1' => Decision::approve()]);
        $result = $verdict->approvals()->withinApprovedToolCalls(
            $decisions,
            fn () => $verdict->runBound($envelope),
        );

        $this->assertTrue($result->executed);
        $this->assertSame(1, Refund::count());
        $this->assertSame(OrderStatus::Refunded, Order::where('number', 'ORD-1001')->firstOrFail()->status);
        $this->assertSame('Damaged in transit', Refund::sole()->reason);

        // The consumed receipt is spent: replaying the same approved call pauses
        // again instead of executing, and no second refund row appears.
        $replay = $verdict->approvals()->withinApprovedToolCalls(
            $decisions,
            fn () => $verdict->runBound($envelope),
        );

        $this->assertFalse($replay->executed);
        $this->assertSame(1, Refund::count());
    }

    public function test_a_cross_principal_refund_is_denied(): void
    {
        $alice = User::factory()->create();
        $bruno = User::factory()->create();
        Order::factory()->for($bruno)->create(['number' => 'ORD-2001', 'status' => OrderStatus::Delivered]);

        $result = app(VerdictManager::class)->runBound($this->refundEnvelope($alice, 'ORD-2001'));

        $this->assertFalse($result->executed);
        $this->assertSame(TargetNotResolvable::DECISION_REASON, $result->evaluation->decision->reason);
        $this->assertSame(0, Refund::count());
    }

    public function test_the_policy_refuses_a_refund_before_delivery(): void
    {
        $alice = User::factory()->create();
        Order::factory()->for($alice)->create(['number' => 'ORD-1003', 'status' => OrderStatus::Paid]);

        $evaluation = app(VerdictManager::class)->evaluate($this->refundEnvelope($alice, 'ORD-1003'));

        $this->assertSame(Disposition::Deny, $evaluation->decision->disposition);
        $this->assertSame(0, Refund::count());
    }

    private function customerWithDeliveredOrder(): User
    {
        $alice = User::factory()->create();
        Order::factory()->for($alice)->create([
            'number' => 'ORD-1001',
            'status' => OrderStatus::Delivered,
            'total_cents' => 10000,
        ]);

        return $alice;
    }

    private function refundEnvelope(User $actor, string $orderNumber): ActionEnvelope
    {
        return ActionEnvelope::wrap(
            new ActionProposal(
                capability: 'orders.refund',
                arguments: ['order_number' => $orderNumber, 'reason' => 'Damaged in transit'],
                idempotencyKey: 'call-refund-1',
            ),
            new ActionContext(actor: $actor),
        );
    }
}
