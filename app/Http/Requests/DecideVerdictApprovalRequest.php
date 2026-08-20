<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\DB;

final class DecideVerdictApprovalRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Only reviewers decide, and only for tool calls this application's own
        // conversation store knows — a receipt id alone is not enough.
        return ($this->user()?->is_reviewer ?? false)
            && DB::table('agent_conversation_messages')
                ->where('tool_calls', 'like', '%'.$this->string('tool_call_id')->toString().'%')
                ->exists();
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
