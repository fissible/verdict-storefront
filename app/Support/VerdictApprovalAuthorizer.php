<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Fissible\Verdict\Approvals\ApprovalDecisionKind;
use Fissible\Verdict\Approvals\ApprovalReceipt;
use Fissible\Verdict\Contracts\ApprovalDecisionAuthorizer;

/**
 * Decides whether a specific reviewer may finalize a specific receipt
 * (verdict#305, docs/security-model.md § "Who may decide a receipt").
 * Registered under verdict.approvals.authorizer; ApprovalManager consults it
 * inside approve()/reject() and refuses every decision (fail-closed) while
 * none is configured — the check this app previously did at the HTTP layer
 * now travels ON the receipt, where the artisan and recorder paths share it.
 *
 * Divergence from the published skeleton, stated plainly: the skeleton binds
 * tenant + conversation. This app is single-tenant, and the conversation id
 * does not exist at proposal time — laravel/ai's RememberConversation
 * middleware persists the conversation AFTER the turn completes, while the
 * receipt is issued mid-turn at the confirmation pause. The binding captured
 * in ActionContext(approvalContext:) is therefore the conversation's
 * PARTICIPANT (customer_id), which the agent knows at proposal time.
 */
final class VerdictApprovalAuthorizer implements ApprovalDecisionAuthorizer
{
    public function authorize(ApprovalReceipt $receipt, ApprovalDecisionKind $kind, string $decidedBy): bool
    {
        // Fail closed on receipts that name no customer: issued before the
        // approval_context migration (null) or issued without identifiers ([]).
        $customerId = $receipt->approvalContext['customer_id'] ?? null;

        if (! is_numeric($customerId) || User::query()->whereKey($customerId)->doesntExist()) {
            return false;
        }

        // Resolve the reviewer FROM $decidedBy — the published controller's
        // 'user:<id>' format — never from request input. Only reviewers decide,
        // for approve and reject alike.
        if (! str_starts_with($decidedBy, 'user:')) {
            return false;
        }

        return User::query()
            ->whereKey(substr($decidedBy, strlen('user:')))
            ->where('is_reviewer', true)
            ->exists();
    }
}
