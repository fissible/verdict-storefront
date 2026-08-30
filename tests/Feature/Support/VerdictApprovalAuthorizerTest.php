<?php

declare(strict_types=1);

namespace Tests\Feature\Support;

use App\Models\User;
use App\Support\VerdictApprovalAuthorizer;
use Fissible\Verdict\Approvals\ApprovalDecisionKind;
use Fissible\Verdict\Approvals\ApprovalReceipt;
use Fissible\Verdict\Approvals\ApprovalReceiptStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The required per-receipt authorization (verdict#305): approved_by is
 * attestation by the application, and this class is where the application
 * makes it mean something — fail-closed on receipts carrying no binding, on
 * unknown actor formats, and on decision makers who are not reviewers.
 */
final class VerdictApprovalAuthorizerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_reviewer_may_decide_a_customer_bound_receipt(): void
    {
        $customer = User::factory()->create();
        $sam = User::factory()->reviewer()->create();

        foreach (ApprovalDecisionKind::cases() as $kind) {
            $this->assertTrue((new VerdictApprovalAuthorizer)->authorize(
                $this->receipt(['customer_id' => $customer->id]),
                $kind,
                'user:'.$sam->id,
            ));
        }
    }

    public function test_it_fails_closed_without_a_binding_or_a_reviewer(): void
    {
        $customer = User::factory()->create();
        $sam = User::factory()->reviewer()->create();
        $authorize = fn (?array $context, string $decidedBy): bool => (new VerdictApprovalAuthorizer)
            ->authorize($this->receipt($context), ApprovalDecisionKind::Approve, $decidedBy);

        // Receipts with no binding: pre-migration (null) or none captured ([]).
        $this->assertFalse($authorize(null, 'user:'.$sam->id));
        $this->assertFalse($authorize([], 'user:'.$sam->id));
        // A binding naming no known customer.
        $this->assertFalse($authorize(['customer_id' => 999999], 'user:'.$sam->id));
        // A decision maker who is not a reviewer, or not a user at all.
        $this->assertFalse($authorize(['customer_id' => $customer->id], 'user:'.$customer->id));
        $this->assertFalse($authorize(['customer_id' => $customer->id], 'recorder:demo'));
        $this->assertFalse($authorize(['customer_id' => $customer->id], 'user:999999'));
    }

    /** @param ?array<string, string|int> $approvalContext */
    private function receipt(?array $approvalContext): ApprovalReceipt
    {
        return new ApprovalReceipt(
            id: 'receipt-1',
            toolCallId: 'call-1',
            capability: 'orders.refund',
            bindingFingerprint: 'fp',
            provenance: null,
            approvalContext: $approvalContext,
            status: ApprovalReceiptStatus::Pending,
            reason: null,
            expiresAt: now()->addMinutes(5)->toImmutable(),
            approvedBy: null,
            approvedAt: null,
            rejectedBy: null,
            rejectedAt: null,
            consumedAt: null,
            createdAt: now()->toImmutable(),
            updatedAt: now()->toImmutable(),
        );
    }
}
