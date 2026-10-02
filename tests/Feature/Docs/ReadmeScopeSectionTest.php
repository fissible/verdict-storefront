<?php

declare(strict_types=1);

namespace Tests\Feature\Docs;

use Tests\TestCase;

/**
 * The scope guard (#27): the README must carry a scope section saying what
 * this app deliberately does NOT demonstrate, so a reader never mistakes the
 * demo's boundary for Verdict's. Assertions are scoped to that section (a
 * scattering of mentions elsewhere proves nothing), require exclusion
 * language rather than mere mentions, and pin the sentences' actual claims.
 */
final class ReadmeScopeSectionTest extends TestCase
{
    private string $section;

    protected function setUp(): void
    {
        parent::setUp();

        $readme = (string) file_get_contents(base_path('README.md'));
        // The scope section, heading through the next ## heading.
        preg_match('/^##[ \t]+[^\n]*\bscope\b[^\n]*\n(.*?)(?=^#{1,2}[ \t]|\z)/msi', $readme, $m);
        $this->section = $m[1] ?? '';
    }

    public function test_a_scope_section_exists(): void
    {
        $this->assertNotSame('', $this->section, 'README carries a "## … scope …" section.');
    }

    public function test_each_unexercised_feature_is_excluded_not_merely_mentioned(): void
    {
        // One negation applying to the list is enough, but it must be a
        // negation in the same sentence as the features, not a nearby "not".
        $this->assertMatchesRegularExpression(
            '/do(es)? not demonstrate[^.]*review lane[^.]*\(`requireReview`\)[^.]*write-ahead intents\b[^.]*attested issuance\b/is',
            $this->section,
            'The review lane (requireReview), write-ahead intents, and attested issuance are excluded in one explicit sentence.',
        );
        $this->assertMatchesRegularExpression(
            '/keyed consumed-binding guard[^.]*\b(not|outside the scope|out of scope)\b|\b(not|does not|outside the scope|out of scope)\b[^.]*keyed consumed-binding guard/is',
            $this->section,
            'The KEYED consumed-binding guard is named as out of scope (the keyless default is what runs).',
        );
    }

    public function test_the_fresh_database_validate_message_is_explained_coherently(): void
    {
        // One explanatory block: the fresh-database context and the quoted
        // INFO in the same paragraph, with the recording-on-first-run causal
        // clause in that paragraph too — not scattered across headings.
        $paragraph = collect(preg_split('/\n\s*\n/', $this->section) ?: [])
            ->first(fn (string $p): bool => str_contains($p, 'no applicable capability configuration'));

        $this->assertNotNull($paragraph, 'The scope section quotes the fresh-database validate INFO.');
        $this->assertMatchesRegularExpression('/fresh (database|clone|install)/i', $paragraph, 'The quote is tied to the fresh-database context.');
        $this->assertMatchesRegularExpression('/\bexpected\b|not a (failure|warning|problem)/i', $paragraph, 'The reader is told the message is expected, not a failure.');
        $this->assertMatchesRegularExpression('/capability configurations?[^.]*record(ed|s)?[^.]*first[^.]*(run|runs|executes)|record(ed|s)?[^.]*capability configurations?[^.]*first[^.]*(run|runs|executes)/i', $paragraph, 'The WHY names its subject: capability configurations are recorded when capabilities first run.');
    }

    public function test_the_live_mode_ci_boundary_is_stated(): void
    {
        $paragraph = collect(preg_split('/\n\s*\n/', $this->section) ?: [])
            ->first(fn (string $p): bool => preg_match('/live mode/i', $p) === 1);

        $this->assertNotNull($paragraph, 'The scope section has a live-mode paragraph.');
        $this->assertMatchesRegularExpression(
            '/live mode is (not|never)(?! only)[^.]*\bCI\b/i',
            $paragraph,
            'The claim is the negative one: live mode is NOT exercised in CI.',
        );
        $this->assertMatchesRegularExpression(
            '/PHP[^.]*Composer[^.]*only|only[^.]*PHP[^.]*Composer/i',
            $paragraph,
            'The reason is the clone-and-run PHP+Composer-only bar.',
        );
        $recordSentence = collect(preg_split('/(?<=[.!?])\s+/', $paragraph) ?: [])
            ->first(fn (string $sent): bool => str_contains($sent, 'demo:record-replays'));

        $this->assertNotNull($recordSentence, 'The paragraph names demo:record-replays.');
        $this->assertMatchesRegularExpression('/\blive\b/i', $recordSentence, 'The record-replays sentence is about live validation.');
        // The command token itself contains 'record'; strip it so only prose
        // can satisfy the guidance-verb requirement.
        $prose = str_replace('demo:record-replays', '', $recordSentence);
        $this->assertMatchesRegularExpression(
            '/\b(run|re-record|records?|validates?|captures?|exercises?)\b/i',
            $prose,
            'The sentence carries the actual guidance verb — deleting the instruction must fail.',
        );
        $this->assertDoesNotMatchRegularExpression('/\b(do not|never|not)\b[^.]*demo:record-replays/i', $recordSentence, 'The guidance is affirmative — run it — not a prohibition.');
    }
}
