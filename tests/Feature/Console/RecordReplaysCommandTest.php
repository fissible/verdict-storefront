<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Tests\TestCase;

/**
 * demo:record-replays only makes sense against a real model: in replay mode
 * it would record the existing fixtures back onto themselves.
 */
final class RecordReplaysCommandTest extends TestCase
{
    public function test_it_refuses_to_record_outside_live_mode(): void
    {
        $this->artisan('demo:record-replays')
            ->expectsOutputToContain('live')
            ->assertFailed();
    }
}
