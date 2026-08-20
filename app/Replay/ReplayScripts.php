<?php

declare(strict_types=1);

namespace App\Replay;

use RuntimeException;

/**
 * The repository of recorded proposals replay mode runs on. Fixtures live in
 * resources/replays as one JSON file per canned prompt; everything downstream
 * of these proposals — Verdict evaluation, policy, confirmation, evidence —
 * runs live against the real database.
 */
final readonly class ReplayScripts
{
    public function __construct(private string $path) {}

    public static function default(): self
    {
        return new self(resource_path('replays'));
    }

    public function find(string $prompt): ?ReplayScript
    {
        foreach ($this->all() as $script) {
            if ($script->prompt === $prompt) {
                return $script;
            }
        }

        return null;
    }

    /** @return list<ReplayScript> */
    public function all(): array
    {
        $files = glob($this->path.'/*.json') ?: [];

        return array_map(function (string $file): ReplayScript {
            $fixture = json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($fixture) || ! is_string($fixture['prompt'] ?? null)) {
                throw new RuntimeException("Replay fixture [{$file}] must declare its prompt.");
            }

            return new ReplayScript(
                prompt: $fixture['prompt'],
                provenance: $fixture['provenance'] ?? [],
                steps: $fixture['steps'] ?? [],
            );
        }, $files);
    }
}
