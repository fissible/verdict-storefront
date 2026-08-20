<?php

declare(strict_types=1);

namespace App\Capabilities\Orders;

use App\Models\Order;
use App\Models\User;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\AuthorizedAction;
use Fissible\Verdict\Capabilities\Capability;
use Fissible\Verdict\Contracts\DefinesCapability;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Fissible\Verdict\Targets\ExecutionTargetPolicy;
use Illuminate\Database\Eloquent\ModelNotFoundException;

/**
 * The headline pattern this app exists to demonstrate (verdict#192, and
 * docs/security-model.md on untrusted arguments): the model's proposed
 * `order_number` is a filter within the authenticated customer's own orders,
 * never a global key. An injected number can change which of the customer's
 * orders is read — always within their authority — but can never reach
 * another principal's record: outside the scope it resolves to nothing, and
 * an unresolvable target is a recorded denial (fail-closed, evidence row
 * included), not an empty result.
 *
 * Implementing DefinesCapability is the registration — discovery finds this
 * class at boot; no provider wiring (docs/adoption-guide.md, "Registering
 * capabilities: affirm, don't wire").
 */
final class LookupCapability implements DefinesCapability
{
    public static function make(): Capability
    {
        return Capability::usingPolicy(
            name: 'orders.lookup',
            ability: 'view',
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
            // Refreshed mutable target (docs/capability-starter-patterns.md): the
            // executor acts on a re-loaded row, re-fetched through the same
            // customer-scoped query, never on the proposal-time instance.
            ->executionTarget(ExecutionTargetPolicy::refresh(
                name: 'orders.lookup-target',
                identityUsing: fn (ActionEnvelope $envelope, Order $target): array => [
                    'customer_id' => $target->user_id,
                    'order_id' => $target->id,
                ],
                refreshUsing: fn (ActionEnvelope $envelope, Order $target): Order => $envelope->context->actor
                    ->orders()
                    ->findOrFail($target->id),
            ))
            ->executeUsing(fn (AuthorizedAction $action): array => self::disclose($action->target));

    }

    /** @return array<string, mixed> */
    private static function disclose(Order $order): array
    {
        return [
            'number' => $order->number,
            'status' => $order->status->value,
            'placed_at' => $order->placed_at->toDateString(),
            'total_cents' => $order->total_cents,
            'items' => $order->items->map(fn ($item): array => [
                'product' => $item->product->name,
                'quantity' => $item->quantity,
                'unit_price_cents' => $item->unit_price_cents,
            ])->all(),
        ];
    }
}
