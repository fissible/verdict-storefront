<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Demo mode
    |--------------------------------------------------------------------------
    |
    | replay (default): an app-owned ReplayGateway drives the real laravel/ai
    | pipeline from recorded proposal steps — no model, no API key, nothing to
    | install. Everything downstream of the model is live: Verdict evaluation,
    | the confirmation pause, the approval round-trip, and real evidence rows.
    |
    | live: ordinary laravel/ai provider configuration (an Anthropic key, or
    | local Ollama with a tools-capable model). See config/ai.php.
    |
    | Replay is never presented as live: every page carries a mode banner, and
    | replay fixtures carry provenance (resources/replays). See the #237 design
    | comment in fissible/verdict.
    |
    */

    'mode' => env('DEMO_MODE', 'replay'),

];
