<?php

declare(strict_types=1);

namespace App\Capabilities\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Models\User;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\AuthorizedAction;
use Fissible\Verdict\Capabilities\Capability;
use Fissible\Verdict\Contracts\DefinesCapability;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Fissible\Verdict\ExecutionClaims\ExecutionClaimPolicy;
use Fissible\Verdict\Targets\ExecutionTargetPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The confirmation-gated mutation (verdict#230/#231: a confirmation gate
 * declares BOTH `requiresConfirmation()` and an execution-target policy —
 * the pause is only meaningful if execution re-validates against refreshed
 * state). The walkthrough's fourth demo — pause → approval → exactly-once
 * completion — is this wiring, per the verified matrix (verdict#233/#235):
 *
 *  - `requiresConfirmation` binds the approval to application-defined facts
 *    (docs/adoption-guide.md § "Evidence profile for a high-consequence
 *    deployment"): approving THIS refund of THIS order at THIS amount, so a
 *    mutated argument after approval invalidates the receipt.
 *  - `ExecutionTargetPolicy::refresh` re-loads the order through the same
 *    customer-scoped query at execution time
 *    (docs/capability-starter-patterns.md § "Refreshed mutable target").
 *  - `atMostOnce` names the business operation — refunding this order in
 *    this state — so a duplicate admission is refused even if a second
 *    approved request races the first
 *    (docs/capability-starter-patterns.md § "One logical operation").
 *
 * Target resolution follows the same scoped-filter discipline as
 * LookupCapability (verdict#192): the proposed order_number selects among
 * the authenticated customer's own orders, never globally.
 */
final class RefundCapability implements DefinesCapability
{
    public static function make(): Capability
    {
        return Capability::usingPolicy(
            name: 'orders.refund',
            ability: 'refund',
            resolveTarget: function (ActionEnvelope $envelope): Order {
                /** @var User $customer */
                $customer = $envelope->context->actor;

                try {
                    return $customer->orders()
                        ->where('number', $envelope->proposal->arguments['order_number'] ?? null)
                        ->firstOrFail();
                } catch (ModelNotFoundException $outsideScope) {
                    throw TargetNotResolvable::make($outsideScope);
                }
            },
        )
            ->requiresConfirmation(
                bindUsing: fn (ActionEnvelope $envelope, Order $order): array => [
                    'customer_id' => $order->user_id,
                    'order_id' => $order->id,
                    'order_status' => $order->status->value,
                    'amount_cents' => $order->total_cents,
                    'reason' => (string) ($envelope->proposal->arguments['reason'] ?? ''),
                ],
                reason: 'Refunds move money; a human confirms this order and amount before execution.',
            )
            ->executionTarget(ExecutionTargetPolicy::refresh(
                name: 'orders.refund-target',
                identityUsing: fn (ActionEnvelope $envelope, Order $target): array => [
                    'customer_id' => $target->user_id,
                    'order_id' => $target->id,
                ],
                refreshUsing: fn (ActionEnvelope $envelope, Order $target): Order => $envelope->context->actor
                    ->orders()
                    ->findOrFail($target->id),
            ))
            ->atMostOnce(ExecutionClaimPolicy::named(
                name: 'customer-order-refund',
                keyUsing: fn (ActionEnvelope $envelope, Order $order): array => [
                    'customer_id' => $order->user_id,
                    'order_id' => $order->id,
                    'order_status' => $order->status->value,
                ],
            ))
            ->executeUsing(function (AuthorizedAction $action): string {
                /** @var Order $order */
                $order = $action->target;

                $refund = $order->refunds()->create([
                    'amount_cents' => $order->total_cents,
                    'reason' => (string) ($action->envelope->proposal->arguments['reason'] ?? ''),
                    'issued_at' => now(),
                ]);
                $order->update(['status' => OrderStatus::Refunded]);

                // A bound tool's result returns to the model as text.
                return json_encode([
                    'refund_id' => $refund->id,
                    'order_number' => $order->number,
                    'amount_cents' => $refund->amount_cents,
                    'status' => OrderStatus::Refunded->value,
                ], JSON_THROW_ON_ERROR);
            });
    }
}
