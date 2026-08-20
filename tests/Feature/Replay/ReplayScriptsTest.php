<?php

declare(strict_types=1);

namespace Tests\Feature\Replay;

use App\Replay\ReplayScripts;
use Tests\TestCase;

/**
 * Replay fixtures are the recorded model proposals the default mode runs on.
 * The integrity rule from the #237 design: every fixture carries provenance —
 * which model produced the recorded proposal, when, and under what prompt —
 * because replay is never presented as live.
 */
final class ReplayScriptsTest extends TestCase
{
    public function test_finds_a_recorded_script_by_its_prompt(): void
    {
        $script = app(ReplayScripts::class)->find('Where is my order ORD-1001?');

        $this->assertNotNull($script);
        $this->assertNotEmpty($script->steps);
        $this->assertSame('ORD-1001', $script->steps[0]['tool_calls'][0]['arguments']['order_number']);
    }

    public function test_an_unrecorded_prompt_finds_nothing(): void
    {
        $this->assertNull(app(ReplayScripts::class)->find('Write me a poem about databases.'));
    }

    public function test_every_fixture_carries_full_provenance(): void
    {
        $scripts = app(ReplayScripts::class)->all();

        $this->assertNotEmpty($scripts);

        foreach ($scripts as $script) {
            foreach (['model', 'recorded_at', 'recorded_under_prompt'] as $fact) {
                $this->assertNotEmpty(
                    $script->provenance[$fact] ?? null,
                    "Replay fixture for [{$script->prompt}] is missing provenance fact [{$fact}].",
                );
            }
        }
    }
}
