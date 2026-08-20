<?php

declare(strict_types=1);

namespace App\Policies;

use App\Enums\OrderStatus;
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

    public function refund(User $customer, Order $order): Response
    {
        if ($customer->id !== $order->user_id) {
            return Response::deny('Order belongs to another customer.');
        }

        return $order->status === OrderStatus::Delivered
            ? Response::allow('Customer owns a delivered order.')
            : Response::deny('Only delivered orders may be refunded.');
    }
}
