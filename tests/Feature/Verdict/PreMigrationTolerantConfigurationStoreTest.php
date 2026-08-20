<?php

declare(strict_types=1);

namespace Tests\Feature\Verdict;

use App\Verdict\PreMigrationTolerantConfigurationStore;
use Fissible\Verdict\VerdictManager;
use Tests\TestCase;

/**
 * The workaround for verdict#240 must hold on a completely fresh clone, where
 * the very first artisan boot (key:generate in `composer run setup`) runs
 * before the SQLite file exists at all — not just before it is migrated.
 */
final class PreMigrationTolerantConfigurationStoreTest extends TestCase
{
    public function test_recording_is_skipped_when_the_database_is_unreachable(): void
    {
        config([
            'database.connections.fresh-clone' => [
                'driver' => 'sqlite',
                'database' => storage_path('framework/testing/does-not-exist.sqlite'),
                'foreign_key_constraints' => true,
            ],
            'verdict.capability_configurations.connection' => 'fresh-clone',
        ]);

        $configuration = app(VerdictManager::class)
            ->registeredCapability('orders.lookup')
            ->configuration();

        app(PreMigrationTolerantConfigurationStore::class)->record($configuration);

        $this->assertFileDoesNotExist(storage_path('framework/testing/does-not-exist.sqlite'));
    }
}
