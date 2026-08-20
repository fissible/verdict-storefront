<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Order;
use App\Models\User;
use Illuminate\Auth\Access\Response;

/**
 * The authorization layer Verdict's ability check runs against
 * (docs/adoption-guide.md: the application owns the Laravel policy). The
 * capability's scoped resolver makes a non-owner unreachable; this policy is
 * the independent second layer, fail-closed on ownership, and its response
 * reasons surface in the recorded decision evidence.
 */
final class OrderPolicy
{
    public function view(User $customer, Order $order): Response
    {
        return $customer->id === $order->user_id
            ? Response::allow('Customer owns this order.')
            : Response::deny('Order belongs to another customer.');
    }
}
