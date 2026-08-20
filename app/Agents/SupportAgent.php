<?php

declare(strict_types=1);

namespace App\Agents;

use App\Ai\Tools\LookupOrderTool;
use App\Ai\Tools\RefundOrderTool;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\LaravelAi\VerdictApprovalMiddleware;
use Fissible\Verdict\VerdictManager;
use Illuminate\Support\Facades\Auth;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasMiddleware;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Contracts\RemembersConversations as RemembersConversationsContract;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Promptable;
use Laravel\Ai\Tools\Request;
use Stringable;

/**
 * The storefront support agent — the app's single Verdict integration point.
 * Its tools are the definitions in app/Ai/Tools wired through Verdict via
 * VerdictManager::bound(): the model proposes, the capability authorizes.
 */
final class SupportAgent implements Agent, HasMiddleware, HasTools, RemembersConversationsContract
{
    // Conversational on purpose: a confirmation pause is only resumable when
    // the pending tool call can be replayed from durable conversation history.
    use Promptable;
    use RemembersConversations;

    public function instructions(): Stringable|string
    {
        return 'You are the support agent for a small storefront. Help the authenticated customer '
            .'with their own orders: look up order status and, when they ask, request refunds. '
            .'Refunds require human approval before they execute.';
    }

    /**
     * Built here rather than held as properties: a BoundTool closes over
     * VerdictManager and the capability's executor closures, and the trusted
     * ActionContext must name the CURRENT authenticated customer — a callable
     * context is resolved fresh on every invocation, including resumes.
     *
     * @return array<int, Tool>
     */
    public function tools(): array
    {
        $verdict = app(VerdictManager::class);
        $context = fn (Request $request): ActionContext => new ActionContext(actor: Auth::user());

        return [
            $verdict->bound(new LookupOrderTool, 'orders.lookup', $context),
            $verdict->bound(new RefundOrderTool, 'orders.refund', $context),
        ];
    }

    /**
     * Required: VerdictApprovalMiddleware is not auto-registered, and without
     * it an approved receipt can never resume (docs/adoption-guide.md).
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [app(VerdictApprovalMiddleware::class)];
    }

    public function provider(): string
    {
        return (string) config('ai.default', 'anthropic');
    }

    public function maxSteps(): int
    {
        return 3;
    }
}
