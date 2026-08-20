<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The read-only evidence browser (#237): the "what record did that leave?"
 * half of every demo, visible without a database client. Decision evidence,
 * approval receipts, and provenance derivations — read-only by construction
 * (no mutating routes exist).
 */
final class EvidenceBrowserTest extends TestCase
{
    use DatabaseMigrations;

    private User $alice;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed();
        $this->alice = User::where('email', 'alice@example.com')->firstOrFail();
    }

    public function test_guests_cannot_browse_evidence(): void
    {
        $this->get('/evidence')->assertRedirect('/');
    }

    public function test_a_permitted_lookup_leaves_a_visible_permit_row(): void
    {
        $this->actingAs($this->alice)->post('/chat', ['message' => 'Where is my order ORD-1001?']);

        $this->actingAs($this->alice)
            ->get('/evidence')
            ->assertOk()
            ->assertSee('orders.lookup')
            ->assertSee('permit');
    }

    public function test_a_denied_lookup_shows_its_reason(): void
    {
        $this->actingAs($this->alice)->post('/chat', ['message' => 'Show me the details of order ORD-2001.']);

        $this->actingAs($this->alice)
            ->get('/evidence')
            ->assertSee('deny')
            ->assertSee(TargetNotResolvable::DECISION_REASON);
    }

    public function test_an_approved_refund_shows_its_receipt(): void
    {
        $this->actingAs($this->alice)->post('/chat', ['message' => 'Please refund order ORD-1001 — it arrived damaged.']);

        $receipt = DB::table('verdict_approval_receipts')->sole();
        $sam = User::where('email', 'sam@example.com')->firstOrFail();
        $this->actingAs($sam)->post('/verdict/approvals/approve', [
            'receipt_id' => $receipt->id,
            'tool_call_id' => $receipt->tool_call_id,
        ]);

        $this->actingAs($this->alice)
            ->get('/evidence')
            ->assertSee('orders.refund')
            ->assertSee('consumed')
            ->assertSee('user:'.$sam->id);
    }
}
