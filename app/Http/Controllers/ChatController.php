<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Agents\SupportAgent;
use App\Replay\ReplayScripts;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

final class ChatController extends Controller
{
    public function show(Request $request, ReplayScripts $scripts): View
    {
        $user = $request->user();

        $conversationId = DB::table('agent_conversations')
            ->where('participant_type', $user::class)
            ->where('participant_id', $user->id)
            ->latest('updated_at')
            ->value('id');

        // rowid keeps insertion order for same-second timestamps; this app is
        // SQLite by design (clone-and-run).
        $messages = $conversationId === null ? collect() : DB::table('agent_conversation_messages')
            ->where('conversation_id', $conversationId)
            ->orderBy('created_at')
            ->orderByRaw('rowid')
            ->get();

        return view('chat', [
            'messages' => $messages,
            'cannedPrompts' => array_map(fn ($script): string => $script->prompt, $scripts->all()),
            // Demo simplification: one storefront, so any pending receipt is
            // "the" pending approval. #7's approval screen lists them properly.
            'awaitingApproval' => DB::table('verdict_approval_receipts')->where('status', 'pending')->exists(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'message' => ['required', 'string', 'max:500'],
        ]);

        (new SupportAgent)
            ->forParticipant($request->user())
            ->prompt($validated['message'], model: config('demo.live_model'));

        return redirect()->route('chat');
    }
}
