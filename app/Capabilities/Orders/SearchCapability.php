<?php

declare(strict_types=1);

namespace App\Capabilities\Orders;

use App\Enums\OrderStatus;
use App\Models\Order;
use App\Verdict\OrderSearchScope;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Actions\ActionEnvelope;
use Fissible\Verdict\Actions\AuthorizedAction;
use Fissible\Verdict\Capabilities\Capability;
use Fissible\Verdict\Contracts\DefinesCapability;
use Fissible\Verdict\Targets\ExecutionTargetPolicy;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

/**
 * The set-returning pattern (verdict#251), beside the record-keyed lookup:
 * "find my orders" has no single target, so the capability resolves a scope
 * bound to the customer, the policy authorizes the scope, and the executor
 * applies the scope as the query predicate BEFORE the model's filters narrow
 * it. The safe outcome of a hostile filter is therefore a *filtered permit*
 * — the tool runs, and another customer's order is simply absent — not a
 * blanket denial.
 *
 * Registered via usingPolicyForContextTarget (ADR 0025): the resolver's
 * parameter type is the guarantee — it receives only the trusted
 * ActionContext, so the model's arguments are not even in scope during target
 * selection — and every evidence row records target_source=context.
 */
final class SearchCapability implements DefinesCapability
{
    public static function make(): Capability
    {
        return Capability::usingPolicyForContextTarget(
            name: 'orders.search',
            ability: 'search',
            resolveTarget: fn (ActionContext $context): OrderSearchScope => OrderSearchScope::forContext($context),
        )
            // Refreshed target (docs/capability-starter-patterns.md), and
            // deterministic by construction: re-resolving from the same trusted
            // context yields the same scope, so a mismatch can only mean the
            // context itself changed between authorization and execution.
            ->executionTarget(ExecutionTargetPolicy::refresh(
                name: 'orders.search-scope',
                identityUsing: fn (ActionEnvelope $envelope, OrderSearchScope $scope): array => [
                    'resource_type' => 'order-search-scope',
                    'customer_id' => $scope->customerId,
                ],
                refreshUsing: fn (ActionEnvelope $envelope, OrderSearchScope $scope): OrderSearchScope => OrderSearchScope::forContext($envelope->context),
            ))
            ->executeUsing(function (AuthorizedAction $action): string {
                if (! $action->target instanceof OrderSearchScope) {
                    throw new LogicException('The search capability expected an order-search scope.');
                }

                return json_encode(
                    self::search($action->target, $action->envelope->proposal->arguments),
                    JSON_THROW_ON_ERROR,
                );
            });
    }

    /**
     * The model's arguments are applied INSIDE the authorized scope: each one
     * can only narrow the result set, never widen it past the customer's own
     * orders. LIKE wildcards in the product term are escaped for the same
     * reason — a supplied `%` must not turn a narrowing filter into a broad one.
     *
     * @param  array<string, mixed>  $filters
     * @return list<array<string, mixed>>
     */
    private static function search(OrderSearchScope $scope, array $filters): array
    {
        $query = $scope->constrain(Order::query())->with('items.product')->orderByDesc('placed_at');

        $rawStatus = strtolower(trim((string) ($filters['status'] ?? '')));
        if ($rawStatus !== '') {
            $status = OrderStatus::tryFrom($rawStatus);

            // An unrecognised status is a filter nothing satisfies — never a
            // filter silently dropped, which would widen the result set.
            if ($status === null) {
                return [];
            }

            $query->where('status', $status);
        }

        $product = trim((string) ($filters['product'] ?? ''));
        if ($product !== '') {
            // An explicit ESCAPE clause with a non-backslash escape character:
            // portable across SQLite, MySQL, and PostgreSQL (whose default
            // escape handling differs), so %, _ and \ in the term match literally.
            $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $product);
            $query->whereHas('items.product', fn (Builder $q) => $q->whereRaw('name like ? escape ?', ["%{$escaped}%", '!']));
        }

        return $query->get()->map(fn (Order $order): array => [
            'number' => $order->number,
            'status' => $order->status->value,
            'placed_at' => $order->placed_at->toDateString(),
            'total_cents' => $order->total_cents,
            'products' => $order->items->map(fn ($item): string => $item->product->name)->all(),
        ])->all();
    }
}
