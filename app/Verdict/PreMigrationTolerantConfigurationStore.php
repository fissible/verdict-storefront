<?php

declare(strict_types=1);

namespace App\Verdict;

use Fissible\Verdict\Capabilities\CapabilityConfiguration;
use Fissible\Verdict\Capabilities\DatabaseCapabilityConfigurationStore;
use Fissible\Verdict\Contracts\CapabilityConfigurationStore;
use Illuminate\Database\DatabaseManager;
use Illuminate\Database\QueryException;

/**
 * Workaround for verdict#240 (delete this class when it is fixed upstream):
 * Verdict records each affirmed capability's configuration fingerprint at
 * boot, and artisan boots before `migrate` runs — so a fresh database cannot
 * be migrated once any capability is affirmed. Delegate to the database
 * store only when its table exists; `insertOrIgnore` makes the very next
 * boot after migration record the row, so nothing is lost, only deferred.
 *
 * https://github.com/fissible/verdict/issues/240
 */
final readonly class PreMigrationTolerantConfigurationStore implements CapabilityConfigurationStore
{
    public function __construct(private DatabaseManager $database) {}

    public function record(CapabilityConfiguration $configuration): void
    {
        $configured = config('verdict.capability_configurations.connection');
        $connection = $this->database->connection(is_string($configured) ? $configured : null);

        $table = config('verdict.capability_configurations.table', 'verdict_capability_configurations');
        $table = is_string($table) ? $table : 'verdict_capability_configurations';

        try {
            if (! $connection->getSchemaBuilder()->hasTable($table)) {
                return;
            }
        } catch (QueryException) {
            // A fresh clone's first boots (key:generate) run before the SQLite
            // file itself exists; an unreachable database defers exactly like a
            // missing table.
            return;
        }

        (new DatabaseCapabilityConfigurationStore($connection, $table))->record($configuration);
    }
}
