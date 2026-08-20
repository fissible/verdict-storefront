@extends('layouts.app')

@section('content')
    <h2 style="font-size:1rem">Try to break it</h2>
    <p class="quiet">
        Injection prompts drawn from Verdict's shipped attack packs. Each button sends the prompt
        through the ordinary chat path — no special handling, no forcing techniques.
        @if (config('demo.mode') === 'live')
            A live aligned model may refuse the bait; that refusal is a result, not a malfunction —
            Verdict's denial is what fires when the model does attempt it.
        @endif
    </p>

    @foreach ($scenarios as $scenario)
        <div class="msg user">
            <div class="who">{{ $scenario['title'] }} <span class="quiet">({{ $scenario['pack'] }})</span></div>
            <div style="font-family:ui-monospace, monospace; font-size:.85rem; margin:.35rem 0">{{ $scenario['prompt'] }}</div>
            <div class="quiet"><strong>What to watch:</strong> {{ $scenario['watch'] }}</div>
            <form method="POST" action="{{ route('chat.store') }}" style="margin-top:.5rem">
                @csrf
                <input type="hidden" name="message" value="{{ $scenario['prompt'] }}">
                <button type="submit">Send this attack</button>
            </form>
        </div>
    @endforeach

    <p class="quiet">
        After sending, read the reply in <a href="{{ route('chat') }}">chat</a> and the denial row in the
        <a href="{{ route('evidence') }}">evidence browser</a>.
    </p>
@endsection
