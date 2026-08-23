<?php

declare(strict_types=1);

namespace App\Verdict;

use App\Models\Order;
use App\Models\User;
use Fissible\Verdict\Actions\ActionContext;
use Fissible\Verdict\Exceptions\TargetNotResolvable;
use Illuminate\Database\Eloquent\Builder;

/**
 * The target of a set-returning capability (verdict#251): a search has no
 * single record for the policy to inspect, so the capability resolves a scope
 * VALUE OBJECT bound to the authenticated customer and the policy authorizes
 * the scope itself. `resolveTarget` returns `mixed`, so Verdict needs no
 * contract for this — the scope is the application's own type, mirrored from
 * the package workbench's reference wiring.
 *
 * Built only from the trusted ActionContext (docs/security-model.md: the
 * model's arguments are never in reach here), and applied by the executor as
 * the query predicate — the tenant filter lives inside the boundary, carried
 * in evidence, instead of in ordinary tool code.
 */
final readonly class OrderSearchScope
{
    private function __construct(public int $customerId) {}

    public static function forContext(ActionContext $context): self
    {
        $actor = $context->actor;

        if (! $actor instanceof User) {
            throw TargetNotResolvable::make();
        }

        return new self($actor->id);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    public function constrain(Builder $query): Builder
    {
        return $query->where('user_id', $this->customerId);
    }
}
