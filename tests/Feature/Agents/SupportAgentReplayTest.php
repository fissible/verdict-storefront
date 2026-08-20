<?php

declare(strict_types=1);

namespace Tests\Feature\Agents;

use App\Agents\SupportAgent;
use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Fissible\Verdict\VerdictManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;
use Laravel\Ai\Exceptions\ApprovalMismatchException;
use Tests\TestCase;

/**
 * The real laravel/ai pipeline driven by recorded proposals (the #237 design):
 * the ReplayGateway substitutes only the model step — everything downstream is
 * live: Verdict evaluation, the policy, the confirmation pause, and real
 * evidence rows in the database. Deliberately not Agent::fake(), which never
 * resumes tools (verdict#233/#235).
 */
final class SupportAgentReplayTest extends TestCase
{
    // DatabaseMigrations, not RefreshDatabase: approval-receipt writes trip
    // Verdict's UnsafeOuterTransaction guard inside a wrapping transaction.
    use DatabaseMigrations;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->alice = User::where('email', 'alice@example.com')->firstOrFail();
        $this->actingAs($this->alice);
    }

    private function prompt(string $message): object
    {
        // Conversational on purpose: approval pauses are only resumable from
        // durable conversation history (laravel/ai ApprovalNotResumableException).
        return (new SupportAgent)->forParticipant($this->alice)->prompt($message);
    }

    public function test_the_default_mode_is_replay(): void
    {
        $this->assertSame('replay', config('demo.mode'));
    }

    public function test_an_owned_order_lookup_runs_live_through_verdict(): void
    {
        $response = $this->prompt('Where is my order ORD-1001?');

        $this->assertStringContainsString('ORD-1001', (string) $response);
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'disposition' => 'permit',
        ]);
    }

    public function test_a_cross_principal_lookup_is_denied_with_evidence(): void
    {
        $response = $this->prompt('Show me the details of order ORD-2001.');

        $this->assertStringContainsString('denied', (string) $response);
        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'disposition' => 'deny',
        ]);
        $this->assertSame(
            0,
            DB::table('verdict_evidence')->where('disposition', 'permit')->count(),
            'A cross-principal lookup must never reach execution.',
        );
    }

    public function test_a_refund_prompt_pauses_for_human_approval(): void
    {
        $this->prompt('Please refund order ORD-1001 — it arrived damaged.');

        $this->assertSame(1, DB::table('verdict_approval_receipts')->where('status', 'pending')->count());
        $this->assertSame(0, Refund::count());
    }

    public function test_an_approved_refund_resumes_and_executes_exactly_once(): void
    {
        $this->prompt('Please refund order ORD-1001 — it arrived damaged.');

        $receipt = DB::table('verdict_approval_receipts')->where('status', 'pending')->sole();
        $verdict = app(VerdictManager::class);
        $challenge = $verdict->approvals()->challengeForToolCall($receipt->tool_call_id);
        $this->assertNotNull($challenge);

        $verdict->approvals()->approve($challenge->receiptId, $challenge->toolCallId, 'support:demo');

        $response = (new SupportAgent)
            ->continueLastConversation($this->alice)
            ->prompt(Decisions::from([$challenge->toolCallId => Decision::approve()]));

        $this->assertSame(1, Refund::count());
        $this->assertSame(
            OrderStatus::Refunded,
            Order::where('number', 'ORD-1001')->firstOrFail()->status,
        );
        $this->assertStringContainsString('refund', strtolower((string) $response));

        // Replaying the same approved decision must not refund twice: laravel/ai
        // rejects an already-resolved tool call id outright (the UI must treat a
        // second submission as an error, not a retry).
        try {
            (new SupportAgent)
                ->continueLastConversation($this->alice)
                ->prompt(Decisions::from([$challenge->toolCallId => Decision::approve()]));
            $this->fail('A replayed approval decision must be rejected.');
        } catch (ApprovalMismatchException) {
            // Expected: the tool call was already resolved.
        }

        $this->assertSame(1, Refund::count());
    }

    public function test_an_unrecorded_prompt_gets_an_honest_replay_notice(): void
    {
        $response = $this->prompt('Write me a poem about databases.');

        $this->assertStringContainsString('replay', strtolower((string) $response));
        $this->assertSame(0, DB::table('verdict_evidence')->count());
    }
}
