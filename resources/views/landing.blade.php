@extends('layouts.app')

@section('content')
    <p>
        A clone-and-run storefront support agent demonstrating correct
        <a href="https://github.com/fissible/verdict">fissible/verdict</a> wiring end-to-end —
        <em>models propose, applications authorize</em>.
    </p>

    <h2 style="font-size:1rem">Sign in as a demo customer</h2>
    <p class="quiet">Synthetic data only; re-seed any time with <code>php artisan migrate:fresh --seed</code>.</p>

    <div class="signin">
        <form method="POST" action="{{ route('demo-login') }}" style="display:inline">
            @csrf
            <input type="hidden" name="email" value="alice@example.com">
            <button type="submit">Alice Storey</button>
        </form>
        <form method="POST" action="{{ route('demo-login') }}" style="display:inline">
            @csrf
            <input type="hidden" name="email" value="bruno@example.com">
            <button type="submit">Bruno Marchetti</button>
        </form>
        <form method="POST" action="{{ route('demo-login') }}" style="display:inline">
            @csrf
            <input type="hidden" name="email" value="sam@example.com">
            <button type="submit">Sam Reyes (reviewer)</button>
        </form>
    </div>

    @error('email')<p class="notice">{{ $message }}</p>@enderror
@endsection
