<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The upgrade probe, automated.
 *
 * This application exists to be an integration fixture for Verdict, and in that role it has now
 * found three defects the package's own suite could not: verdict#240 and verdict#256 (boot-time
 * configuration recording against a table or database that does not exist yet) and verdict#466
 * (a column added to the evidence *create* migration in place, so no published migration could
 * give it to an install that already ran that migration).
 *
 * Every one of those was caught by a person running `php artisan verdict:validate` by hand after a
 * pin bump and reading the output. That is the part this test replaces. `verdict:validate` is
 * Verdict's own audit of whether a deployment's schema matches what the installed version writes,
 * and this application's published migrations are a real deployment's worth of history — some
 * published before v0.15.0, some after. Running the audit against them on every CI run is the
 * cheapest possible version of the check that has been doing the work.
 *
 * The second test is why the first one means anything. An audit that has quietly stopped inspecting
 * — a renamed table, a recorder that no longer resolves, a swallowed exception — reports success
 * exactly like a correct schema does, and a green "it exits 0" would sail through every one of
 * those. So the control drops a column the audit is known to require and asserts that it says so,
 * naming the column. Only then does the clean run mean the schema is current rather than the audit
 * being asleep.
 */
final class VerdictSchemaIsCurrentTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_migrated_schema_satisfies_verdicts_own_audit(): void
    {
        // RefreshDatabase runs this application's real published migration chain, which is the
        // subject: not a schema built to match the package, but the one an operator would have.
        $this->artisan('verdict:validate')->assertSuccessful();
    }

    public function test_the_audit_still_reports_an_evidence_column_this_application_is_missing(): void
    {
        // review_request_fingerprint is verdict#466's column, and carries no index, so SQLite can
        // drop it. Any column the audit requires would do; this one keeps the control pointed at
        // the defect that motivated the test.
        //
        // Stated as a precondition rather than assumed: if the column is absent, the first test is
        // already failing for the real reason, and without this the control adds only an opaque
        // "no such column" from the DROP. A legible message beats a SQL error on the run where
        // someone is trying to work out what broke.
        $this->assertTrue(
            Schema::hasColumn('verdict_evidence', 'review_request_fingerprint'),
            'This control drops a column the audit requires, and the schema does not have it — '
                .'see the failure above rather than this one.',
        );

        Schema::table('verdict_evidence', function (Blueprint $table): void {
            $table->dropColumn('review_request_fingerprint');
        });

        $this->artisan('verdict:validate')
            ->expectsOutputToContain('review_request_fingerprint')
            ->assertFailed();
    }
}
