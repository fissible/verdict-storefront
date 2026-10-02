<?php

declare(strict_types=1);

namespace Tests\Feature\Capabilities\Orders;

use App\Models\User;
use DateInterval;
use DateTimeImmutable;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\ActionProposal;
use Fissible\Verdict\Contracts\Clock;
use Fissible\Verdict\Contracts\RateLimitStore;
use Fissible\Verdict\Decisions\Disposition;
use Fissible\Verdict\RateLimits\RateLimitManager;
use Fissible\Verdict\VerdictManager;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The rate-limited read (#28): the configured DatabaseRateLimitStore stops
 * being decoration. orders.search declares a fixed-window policy keyed by the
 * authenticated customer — the key is the ACTOR's identity, never the model's
 * arguments, so varied filters share one quota and an injected identifier
 * cannot mint a fresh one. Past the limit the disposition is throttle with
 * the policy's reason — the package renders a non-executed tool result as an
 * explicit not_executed payload, never a silent empty list (that rendering is
 * AbstractVerdictTool's tested contract, not re-tested here).
 */
final class SearchRateLimitTest extends TestCase
{
    // DatabaseMigrations, not RefreshDatabase: DatabaseRateLimitStore refuses
    // a wrapping transaction (UnsafeOuterTransaction), like the approval store.
    use DatabaseMigrations;

    private const LIMIT = 10;

    private const WINDOW_SECONDS = 60;

    /** Frozen for every test — real time crossing a window boundary mid-test would falsely restore the allowance. */
    private FrozenClock $clock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->clock = new FrozenClock(new DateTimeImmutable('2026-10-02 12:00:00'));
        $this->app->instance(Clock::class, $this->clock);
        $this->rebuildLimiterStack();
    }

    public function test_the_capability_declares_the_search_rate_limit(): void
    {
        $policy = app(VerdictManager::class)
            ->registeredCapability('orders.search')
            ->rateLimitPolicy();

        $this->assertNotNull($policy, 'orders.search declares a rate-limit policy — the configured store must be exercised.');
        $this->assertNotSame('', trim($policy->name));
        $this->assertSame(self::LIMIT, $policy->limit);
        $this->assertSame(self::WINDOW_SECONDS, $policy->windowSeconds);
        $this->assertNotNull($policy->reason, 'The throttle decision must carry a human-readable reason.');
    }

    public function test_searches_within_the_window_are_unthrottled(): void
    {
        $alice = User::factory()->create();

        foreach ($this->variedFilters(self::LIMIT) as $i => $filters) {
            $result = app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters));
            $this->assertTrue($result->executed, "Search {$i} within the window must execute.");
        }

        $this->assertSame(0, DB::table('verdict_evidence')->where('disposition', 'throttle')->count());
    }

    public function test_the_search_past_the_limit_is_throttled_with_evidence(): void
    {
        $alice = User::factory()->create();

        foreach ($this->variedFilters(self::LIMIT) as $filters) {
            app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters));
        }

        // Rebuild the whole limiter stack and reload the actor: the quota must
        // live in the durable store, not in object identity or any in-process
        // accumulation (manager, limiter, or store instance).
        $this->rebuildLimiterStack();
        $aliceReloaded = User::query()->findOrFail($alice->id);

        $result = app(VerdictManager::class)->runBound($this->searchEnvelope($aliceReloaded, ['product' => 'entirely new term']));

        $this->assertFalse($result->executed, 'The over-limit search must not execute.');
        $this->assertNull($result->output, 'A throttled search returns no result set — never a silent empty one.');
        $this->assertSame(Disposition::Throttle, $result->evaluation->decision->disposition);
        $this->assertNotSame('', (string) $result->evaluation->decision->reason, 'The decision surfaces the policy reason.');

        // Stage-scoped (the proposal/refresh/execution stages record their own
        // permits): exactly LIMIT rate-limit permits, exactly one throttle.
        $this->assertSame(self::LIMIT, DB::table('verdict_evidence')->where('capability', 'orders.search')->where('stage', 'rate_limit')->where('disposition', 'permit')->count());
        $this->assertSame(1, DB::table('verdict_evidence')->where('capability', 'orders.search')->where('stage', 'rate_limit')->where('disposition', 'throttle')->count());
        $this->assertGreaterThan(0, DB::table('verdict_rate_limit_buckets')->count(), 'The bucket is durable state in the configured store.');
    }

    public function test_an_injected_identifier_cannot_mint_a_fresh_quota(): void
    {
        // The model's arguments are untrusted; a hostile filter naming another
        // customer must spend the ACTOR's quota, not open someone else's.
        $alice = User::factory()->create();
        $bruno = User::factory()->create();

        foreach ($this->variedFilters(self::LIMIT) as $filters) {
            app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters));
        }

        $result = app(VerdictManager::class)->runBound($this->searchEnvelope($alice, [
            'product' => "Bruno's order",
            // Bruno's REAL identifiers: a key built from the model's arguments
            // instead of the actor would mint Bruno's fresh quota here.
            'customer_id' => (string) $bruno->id,
            'customer_email' => $bruno->email,
        ]));

        $this->assertFalse($result->executed, 'Argument variation must not replenish the quota.');
        $this->assertSame(Disposition::Throttle, $result->evaluation->decision->disposition);
    }

    public function test_one_customers_burst_does_not_throttle_another(): void
    {
        $alice = User::factory()->create();
        $bruno = User::factory()->create();

        foreach ($this->variedFilters(self::LIMIT + 1) as $filters) {
            app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters));
        }

        foreach ($this->variedFilters(self::LIMIT) as $i => $filters) {
            $result = app(VerdictManager::class)->runBound($this->searchEnvelope($bruno, $filters));
            $this->assertTrue($result->executed, "Bruno's search {$i} gets his full independent allowance.");
        }
    }

    public function test_the_window_elapsing_restores_the_allowance(): void
    {
        $alice = User::factory()->create();
        $start = $this->clock->now;

        foreach ($this->variedFilters(self::LIMIT) as $filters) {
            app(VerdictManager::class)->runBound($this->searchEnvelope($alice, $filters));
        }

        // One second before the boundary: still throttled.
        $this->clock->now = $start->add(new DateInterval('PT'.(self::WINDOW_SECONDS - 1).'S'));
        $this->assertFalse(app(VerdictManager::class)->runBound($this->searchEnvelope($alice))->executed);

        // At the boundary exactly: a fresh allowance.
        $this->clock->now = $start->add(new DateInterval('PT'.self::WINDOW_SECONDS.'S'));
        $this->assertTrue(
            app(VerdictManager::class)->runBound($this->searchEnvelope($alice))->executed,
            'An elapsed window restores the allowance.',
        );
    }

    /**
     * Forget every in-process holder of rate-limit state (and of the Clock),
     * so the next resolution reads only what the database remembers.
     */
    private function rebuildLimiterStack(): void
    {
        $this->app->forgetInstance(RateLimitManager::class);
        $this->app->forgetInstance(RateLimitStore::class);
        $this->app->forgetInstance(VerdictManager::class);
    }

    /** @return list<array<string, string>> */
    private function variedFilters(int $count): array
    {
        return array_map(
            fn (int $i): array => $i % 2 === 0 ? ['product' => "term-{$i}"] : ['status' => 'shipped'],
            range(0, $count - 1),
        );
    }

    /** @param array<string, mixed> $filters */
    private function searchEnvelope(User $actor, array $filters = []): ActionEnvelope
    {
        return ActionEnvelope::wrap(
            new ActionProposal('orders.search', $filters),
            new ActionContext(actor: $actor, approvalContext: ['customer_id' => (int) $actor->id]),
        );
    }
}

/** A test clock the limiter stack reads; tests mutate $now to travel. */
final class FrozenClock implements Clock
{
    public function __construct(public DateTimeImmutable $now) {}

    public function now(): DateTimeImmutable
    {
        return $this->now;
    }
}
