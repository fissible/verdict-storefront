<?php

declare(strict_types=1);

namespace App\Agents;

use App\Ai\Tools\LookupOrderTool;
use App\Ai\Tools\RefundOrderTool;
use App\Ai\Tools\SearchOrdersTool;
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
            .'with their own orders: look up an order by number, search their orders by status or '
            .'product, and, when they ask, request refunds. '
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

        // The conversation's participant, not Auth::user(): on a reviewer-driven
        // resume the authenticated user is the approver, but the capability's
        // scoped queries must resolve inside the CUSTOMER's order authority.
        // approvalContext is the application-owned binding a receipt carries
        // (verdict#305): the customer, not the conversation — the conversation
        // id does not exist yet at a first-turn confirmation pause (laravel/ai
        // persists it after the turn), and VerdictApprovalAuthorizer fails
        // closed on receipts that carry no binding.
        $context = function (Request $request): ActionContext {
            $customer = $this->conversationUser ?? Auth::user();

            return new ActionContext(
                actor: $customer,
                approvalContext: $customer === null ? [] : ['customer_id' => (int) $customer->getKey()],
            );
        };

        return [
            $verdict->bound(new LookupOrderTool, 'orders.lookup', $context),
            $verdict->bound(new SearchOrdersTool, 'orders.search', $context),
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
