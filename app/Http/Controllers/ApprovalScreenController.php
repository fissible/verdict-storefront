<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\User;
use Fissible\Verdict\Approvals\ApprovalManager;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Lists pending approvals: Verdict's challenge summary (which deliberately
 * excludes raw arguments) combined with application-owned display context
 * from the app's own conversation store — the composition the approval-flow
 * skeleton prescribes (docs/verdict-approval-flow.md).
 */
final class ApprovalScreenController extends Controller
{
    public function __invoke(ApprovalManager $approvals): View
    {
        $pending = DB::table('verdict_approval_receipts')
            ->where('status', 'pending')
            ->orderBy('created_at')
            ->get()
            ->map(function (object $receipt) use ($approvals): ?array {
                $challenge = $approvals->challengeForToolCall($receipt->tool_call_id);

                if ($challenge === null) {
                    return null;
                }

                $paused = DB::table('agent_conversation_messages')
                    ->where('tool_calls', 'like', '%'.$receipt->tool_call_id.'%')
                    ->latest('created_at')
                    ->first();

                $call = collect(json_decode($paused->tool_calls ?? '[]', true) ?: [])
                    ->firstWhere('id', $receipt->tool_call_id);

                return [
                    'challenge' => $challenge,
                    'arguments' => $call['arguments'] ?? [],
                    'customer' => $paused?->participant_id !== null
                        ? User::find($paused->participant_id)?->name
                        : null,
                ];
            })
            ->filter();

        return view('approvals', ['pending' => $pending]);
    }
}
