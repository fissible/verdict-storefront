<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use App\Verdict\OrderSearchScope;
use Illuminate\Auth\Access\Response;

/**
 * The policy a scope-as-target capability runs against (verdict#251): the
 * ability is checked on the resolved SCOPE, not on a record — "may this
 * customer search within this scope" — and the response reason surfaces in
 * the recorded decision evidence. Registered explicitly in
 * AppServiceProvider: the scope is not an Eloquent model, so Laravel's policy
 * auto-discovery does not find it.
 */
final class OrderSearchScopePolicy
{
    public function search(User $customer, OrderSearchScope $scope): Response
    {
        return $customer->id === $scope->customerId
            ? Response::allow('Customer searches within their own order scope.')
            : Response::deny("The scope belongs to customer {$scope->customerId}.");
    }
}
