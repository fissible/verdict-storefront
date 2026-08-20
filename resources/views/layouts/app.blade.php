<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>verdict-storefront</title>
    <style>
        :root { color-scheme: light dark; }
        body { font-family: ui-sans-serif, system-ui, sans-serif; max-width: 44rem; margin: 0 auto; padding: 1rem; line-height: 1.5; }
        a { color: inherit; }
        .banner { padding: .6rem .9rem; border-radius: .5rem; font-size: .9rem; margin-bottom: 1.25rem; border: 1px solid; }
        .banner.replay { background: rgba(96,165,250,.12); border-color: rgba(96,165,250,.5); }
        .banner.live { background: rgba(251,146,60,.12); border-color: rgba(251,146,60,.55); }
        .msg { margin: .75rem 0; padding: .6rem .9rem; border-radius: .5rem; }
        .msg.user { background: rgba(148,163,184,.15); }
        .msg.assistant { background: rgba(52,211,153,.12); }
        .msg .who { font-size: .75rem; opacity: .7; text-transform: uppercase; letter-spacing: .04em; }
        .toolcall { font-size: .8rem; font-family: ui-monospace, monospace; opacity: .8; margin-top: .3rem; }
        .notice { padding: .6rem .9rem; border-radius: .5rem; background: rgba(250,204,21,.15); border: 1px solid rgba(250,204,21,.5); margin: 1rem 0; }
        .prompts button, form.chat button { cursor: pointer; }
        .prompts button { display: block; width: 100%; text-align: left; margin: .35rem 0; padding: .5rem .75rem; border-radius: .4rem; border: 1px solid rgba(148,163,184,.5); background: transparent; color: inherit; }
        form.chat { display: flex; gap: .5rem; margin-top: 1rem; }
        form.chat input { flex: 1; padding: .5rem .75rem; border-radius: .4rem; border: 1px solid rgba(148,163,184,.5); background: transparent; color: inherit; }
        form.chat button, .signin button { padding: .5rem 1rem; border-radius: .4rem; border: 1px solid rgba(148,163,184,.5); background: transparent; color: inherit; }
        header { display: flex; justify-content: space-between; align-items: baseline; margin-bottom: .5rem; }
        header h1 { font-size: 1.1rem; margin: 0; }
        .quiet { opacity: .7; font-size: .85rem; }
    </style>
</head>
<body>
<header>
    <h1><a href="{{ url('/') }}" style="text-decoration:none">verdict-storefront</a></h1>
    @auth
        <span class="quiet">
            <a href="{{ route('chat') }}">chat</a> ·
            <a href="{{ route('evidence') }}">evidence</a>
            @if (auth()->user()->is_reviewer) · <a href="{{ route('approvals') }}">approvals</a>@endif
            · {{ auth()->user()->name }}
        </span>
    @endauth
</header>

{{-- The integrity rule (#237 design): replay is never presented as live. --}}
@if (config('demo.mode') === 'replay')
    <div class="banner replay">
        <strong>Replay mode</strong> — the model's proposals are recorded fixtures
        (<a href="https://github.com/fissible/verdict-storefront/tree/main/resources/replays">provenance</a>);
        no model is running. Everything downstream is live: Verdict evaluation, approvals, and the
        evidence rows they leave.
    </div>
@else
    <div class="banner live">
        <strong>Live mode</strong> — a real model
        ({{ config('demo.live_model') ?? 'provider default' }} via {{ config('ai.default') }})
        proposes actions; Verdict authorizes them. An aligned model may refuse the attack prompts —
        that refusal is a result, not a malfunction.
    </div>
@endif

{{ $slot ?? '' }}
@yield('content')
</body>
</html>
