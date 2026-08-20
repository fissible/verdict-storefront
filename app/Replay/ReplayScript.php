<?php

declare(strict_types=1);

namespace App\Replay;

/**
 * One recorded conversation: the canned user prompt, the proposal steps the
 * "model" makes (tool calls, then a closing message), and the provenance the
 * integrity rule requires (#237 design: replay is never presented as live,
 * and every fixture says what produced it, when, and under what prompt).
 *
 * @phpstan-type Step array{text?: string, tool_calls?: list<array{id: string, name: string, arguments: array<string, mixed>}>}
 */
final readonly class ReplayScript
{
    /**
     * @param  array<string, string>  $provenance
     * @param  list<Step>  $steps
     */
    public function __construct(
        public string $prompt,
        public array $provenance,
        public array $steps,
    ) {}
}
