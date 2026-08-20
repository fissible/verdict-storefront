<?php

declare(strict_types=1);

namespace App\Support;

use App\Agents\SupportAgent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Laravel\Ai\Approvals\Decision;
use Laravel\Ai\Approvals\Decisions;

/**
 * The application-owned resume strategy the approval-flow skeleton leaves to
 * the application (docs/verdict-approval-flow.md): after a decision transition
 * succeeds, replay the paused conversation AS ITS OWN PARTICIPANT with the
 * reviewer's explicit per-call decision. The participant matters: the bound
 * tools' ActionContext names the conversation's customer, so the refreshed
 * target re-resolves inside THEIR order scope — never the reviewer's.
 */
final class ResumesApprovedConversations
{
    public function resume(string $toolCallId, Decision $decision): void
    {
        $paused = DB::table('agent_conversation_messages')
            ->where('tool_calls', 'like', '%'.$toolCallId.'%')
            ->latest('created_at')
            ->first();

        if ($paused === null) {
            return;
        }

        /** @var class-string<Model> $participantType */
        $participantType = $paused->participant_type;
        $customer = $participantType::query()->findOrFail($paused->participant_id);

        (new SupportAgent)
            ->continue($paused->conversation_id, $customer)
            ->prompt(Decisions::from([$toolCallId => $decision]));
    }
}
