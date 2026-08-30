<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class DecideVerdictApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Route-level gate: only reviewers reach the decision endpoints. The
        // binding check — does this receipt belong to a customer this app
        // knows — now travels ON the receipt and is enforced fail-closed by
        // VerdictApprovalAuthorizer inside ApprovalManager (verdict#305),
        // where the artisan and recorder paths share it too.
        return $this->user()?->is_reviewer ?? false;
    }

    /** @return array<string, list<string>> */
    public function rules(): array
    {
        return [
            'receipt_id' => ['required', 'string', 'max:255'],
            'tool_call_id' => ['required', 'string', 'max:255'],
        ];
    }
}
