@extends('layouts.app')

@section('content')
    @foreach ($messages as $message)
        @php
            /* laravel/ai 1.0: an assistant turn stores its round trips in a
               steps column; each proposal is a tool call inside its step. */
            $proposals = collect(json_decode($message->steps ?? '[]', true) ?: [])
                ->flatMap(fn (array $step): array => $step['tool_calls'] ?? []);
        @endphp
        @if (in_array($message->role, ['user', 'assistant'], true) && (filled($message->content) || $proposals->isNotEmpty()))
            <div class="msg {{ $message->role }}">
                <div class="who">{{ $message->role === 'user' ? auth()->user()->name : 'Support agent' }}</div>
                @if (filled($message->content))<div>{{ $message->content }}</div>@endif
                @foreach ($proposals as $call)
                    <div class="toolcall">→ proposed {{ $call['name'] ?? 'tool' }}({{ json_encode($call['arguments'] ?? []) }})</div>
                @endforeach
            </div>
        @endif
    @endforeach

    @if ($awaitingApproval)
        <div class="notice">
            A consequential action is <strong>waiting for human approval</strong> before it executes.
            The approval screen arrives with issue #7; approve via <code>php artisan tinker</code> for now.
        </div>
    @endif

    <div class="prompts">
        <p class="quiet">Recorded walkthrough prompts{{ config('demo.mode') === 'replay' ? ' — replay mode answers exactly these' : '' }}:</p>
        @foreach ($cannedPrompts as $prompt)
            <form method="POST" action="{{ route('chat.store') }}">
                @csrf
                <input type="hidden" name="message" value="{{ $prompt }}">
                <button type="submit">{{ $prompt }}</button>
            </form>
        @endforeach
    </div>

    <form class="chat" method="POST" action="{{ route('chat.store') }}">
        @csrf
        <input name="message" placeholder="Ask the support agent…" required maxlength="500">
        <button type="submit">Send</button>
    </form>
@endsection
