<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\Refund;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The customer-facing support chat (#237: server-rendered Blade, no Node
 * build) and the integrity rule's visible half: every page carries a mode
 * banner, and replay is never presented as live.
 */
final class ChatTest extends TestCase
{
    use DatabaseMigrations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed();
    }

    private function alice(): User
    {
        return User::where('email', 'alice@example.com')->firstOrFail();
    }

    public function test_guests_are_sent_to_the_landing_page(): void
    {
        $this->get('/chat')->assertRedirect('/');
    }

    public function test_the_landing_page_offers_the_demo_customers(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Alice Storey')
            ->assertSee('Bruno Marchetti');
    }

    public function test_demo_login_signs_in_a_seeded_customer_only(): void
    {
        $this->post('/demo-login', ['email' => 'alice@example.com'])->assertRedirect('/chat');
        $this->assertTrue(auth()->check());

        auth()->logout();

        $this->post('/demo-login', ['email' => 'mallory@example.com'])->assertSessionHasErrors('email');
        $this->assertFalse(auth()->check());
    }

    public function test_every_page_carries_the_replay_mode_banner(): void
    {
        $this->get('/')->assertSee('Replay mode', escape: false);

        $this->actingAs($this->alice())->get('/chat')->assertSee('Replay mode', escape: false);
    }

    public function test_the_banner_states_live_mode_when_configured(): void
    {
        config(['demo.mode' => 'live']);

        $this->get('/')->assertSee('Live mode', escape: false)->assertDontSee('Replay mode');
    }

    public function test_the_chat_page_offers_the_recorded_prompts(): void
    {
        $this->actingAs($this->alice())
            ->get('/chat')
            ->assertOk()
            ->assertSee('Where is my order ORD-1001?');
    }

    public function test_sending_a_recorded_prompt_runs_the_agent_and_renders_the_reply(): void
    {
        $this->actingAs($this->alice())
            ->post('/chat', ['message' => 'Where is my order ORD-1001?'])
            ->assertRedirect('/chat');

        $this->actingAs($this->alice())
            ->get('/chat')
            ->assertSee('Where is my order ORD-1001?')
            ->assertSee('Ceramic Pour-Over Set');

        $this->assertDatabaseHas('verdict_evidence', [
            'capability' => 'orders.lookup',
            'disposition' => 'permit',
        ]);
    }

    public function test_a_refund_prompt_shows_the_pending_approval_notice(): void
    {
        $this->actingAs($this->alice())
            ->post('/chat', ['message' => 'Please refund order ORD-1001 — it arrived damaged.'])
            ->assertRedirect('/chat');

        $this->actingAs($this->alice())
            ->get('/chat')
            ->assertSee('waiting for human approval');

        $this->assertSame(1, DB::table('verdict_approval_receipts')->where('status', 'pending')->count());
        $this->assertSame(0, Refund::count());
    }
}
