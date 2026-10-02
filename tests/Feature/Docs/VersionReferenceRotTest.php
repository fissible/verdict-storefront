<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * The de-rot guard (#26): version strings written in prose rot — README's pin
 * line survived five bumps saying ^0.8.0, and PROJECT.md's "self-maintaining"
 * line rotted through its parenthetical. The rule: prose points at the files
 * that are always right (composer.json, VERSION, tags) and never carries an
 * inline version snapshot of its own.
 *
 * Scope, deliberately: PROJECT.md's decision log and session handoffs are
 * dated HISTORICAL records — version literals there are facts about the past
 * and do not rot. Only the header block (everything before the first ##
 * heading) makes present-tense claims, so only it is guarded.
 */
final class VersionReferenceRotTest extends TestCase
{
    /** Any package-version spelling: ^0.8.0, :~0.18, >=1.0, "Verdict v0.18.0". */
    private const PIN_PATTERN = '/(fissible\/verdict|laravel\/ai)\s*[:`"\' ]*[~^><=]*\s*v?\d+\.\d+|Verdict v\d+\.\d+/i';

    public function test_the_readme_carries_no_package_version_literal(): void
    {
        $this->assertDoesNotMatchRegularExpression(
            self::PIN_PATTERN,
            (string) file_get_contents(base_path('README.md')),
            'README must point at composer.json for pins, never quote a version spelling that will rot.',
        );
    }

    public function test_the_readme_pin_guidance_names_its_authoritative_source(): void
    {
        $pinLines = collect(explode("\n", (string) file_get_contents(base_path('README.md'))))
            ->filter(fn (string $l): bool => stripos($l, 'pin') !== false);

        $this->assertTrue(
            $pinLines->isNotEmpty(),
            'README keeps a sentence telling the reader about the Verdict pin.',
        );
        $this->assertTrue(
            $pinLines->contains(fn (string $l): bool => str_contains($l, 'composer.json')),
            'Some pin sentence must name composer.json as where the live pin lives.',
        );
    }

    public function test_project_md_header_carries_no_version_snapshot(): void
    {
        $project = (string) file_get_contents(base_path('PROJECT.md'));
        $header = explode("\n## ", $project, 2)[0];

        $this->assertDoesNotMatchRegularExpression(
            '/v?\d+\.\d+(\.\d+)?/',
            $header,
            'The present-tense header must not snapshot a version; history below it may.',
        );
        $this->assertMatchesRegularExpression(
            '/\*\*Current version:\*\*[^\n]*`VERSION`/',
            $header,
            'The version pointer must direct readers to the VERSION file.',
        );
    }
}
