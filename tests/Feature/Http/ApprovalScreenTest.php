<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The minimal approval screen (#237): lists pending approvals with Verdict's
 * challenge summary plus application-owned display context, and a resume path
 * that completes exactly once per the verified matrix (verdict#233/#235).
 */
final class ApprovalScreenTest extends TestCase
{
    use DatabaseMigrations;

    private User $alice;

    private User $sam;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->alice = User::where('email', 'alice@example.com')->firstOrFail();
        $this->sam = User::where('email', 'sam@example.com')->firstOrFail();
    }

    /** @return array{receipt_id: string, tool_call_id: string} */
    private function pauseARefund(): array
    {
        $this->actingAs($this->alice)
            ->post('/chat', ['message' => 'Please refund order ORD-1001 — it arrived damaged.']);

        $receipt = DB::table('verdict_approval_receipts')->where('status', 'pending')->sole();

        return ['receipt_id' => $receipt->id, 'tool_call_id' => $receipt->tool_call_id];
    }

    public function test_only_reviewers_reach_the_approval_screen(): void
    {
        $this->get('/approvals')->assertRedirect('/');
        $this->actingAs($this->alice)->get('/approvals')->assertForbidden();
        $this->actingAs($this->sam)->get('/approvals')->assertOk();
    }

    public function test_a_pending_refund_is_listed_with_challenge_and_display_context(): void
    {
        $this->pauseARefund();

        $this->actingAs($this->sam)
            ->get('/approvals')
            ->assertSee('orders.refund')
            ->assertSee('ORD-1001')
            ->assertSee('Alice Storey')
            ->assertSee('Refunds move money');
    }

    public function test_approving_resumes_the_conversation_and_executes_exactly_once(): void
    {
        $pending = $this->pauseARefund();

        $this->actingAs($this->sam)
            ->post('/verdict/approvals/approve', $pending)
            ->assertRedirect();

        $this->assertSame(1, Refund::count());
        $this->assertSame(OrderStatus::Refunded, Order::where('number', 'ORD-1001')->firstOrFail()->status);
        $this->assertSame('user:'.$this->sam->id, DB::table('verdict_approval_receipts')->sole()->approved_by);

        // The customer's chat shows the completed turn.
        $this->actingAs($this->alice)->get('/chat')->assertSee('refund', escape: false);
    }

    public function test_a_second_approval_submission_is_an_error_not_a_second_refund(): void
    {
        $pending = $this->pauseARefund();

        $this->actingAs($this->sam)->post('/verdict/approvals/approve', $pending);
        $this->actingAs($this->sam)
            ->post('/verdict/approvals/approve', $pending)
            ->assertRedirect();

        $this->assertSame(1, Refund::count());
    }

    public function test_rejecting_leaves_no_refund_and_resolves_the_receipt(): void
    {
        $pending = $this->pauseARefund();

        $this->actingAs($this->sam)
            ->post('/verdict/approvals/reject', $pending)
            ->assertRedirect();

        $this->assertSame(0, Refund::count());
        $this->assertSame('rejected', DB::table('verdict_approval_receipts')->sole()->status);
        $this->assertSame(OrderStatus::Delivered, Order::where('number', 'ORD-1001')->firstOrFail()->status);
    }

    public function test_customers_cannot_decide_approvals(): void
    {
        $pending = $this->pauseARefund();

        $this->actingAs($this->alice)
            ->post('/verdict/approvals/approve', $pending)
            ->assertForbidden();

        $this->assertSame(0, Refund::count());
    }
}
