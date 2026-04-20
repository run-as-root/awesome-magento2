<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateIssueRenderer;
use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\RepoSummary;
use PHPUnit\Framework\TestCase;

final class CandidateIssueRendererTest extends TestCase
{
    public function test_renders_marker_and_new_candidates_with_count(): void
    {
        $repoA = $this->repo('alpha/beta', 'https://github.com/alpha/beta', 'A thing.', 42);
        $repoB = $this->repo('owner/x', 'https://github.com/owner/x', null, 8);
        $candidates = [
            ['repo' => $repoA, 'suggested_yaml' => 'extensions/_triage.yml'],
            ['repo' => $repoB, 'suggested_yaml' => 'extensions/payment.yml'],
        ];
        $emptyLog = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json');

        $body = (new CandidateIssueRenderer())->render($candidates, $emptyLog, new \DateTimeImmutable('2026-04-20T00:00:00Z'));

        $this->assertStringStartsWith("<!-- candidates-issue-v1 -->\n", $body);
        $this->assertStringContainsString('# Magento 2 Discovery Candidates', $body);
        $this->assertStringContainsString('Weekly scan updated 2026-04-20.', $body);
        $this->assertStringContainsString('## New candidates (2)', $body);
        $this->assertStringContainsString('- [ ] [alpha/beta](https://github.com/alpha/beta) ★42 — A thing. _(suggested: `extensions/_triage.yml`)_', $body);
        $this->assertStringContainsString('- [ ] [owner/x](https://github.com/owner/x) ★8 — _no description_ _(suggested: `extensions/payment.yml`)_', $body);
        $this->assertStringNotContainsString('## Previously decided', $body);
    }

    public function test_empty_candidates_renders_zero_count_and_no_bullets(): void
    {
        $emptyLog = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json');
        $body = (new CandidateIssueRenderer())->render([], $emptyLog, new \DateTimeImmutable('2026-04-20T00:00:00Z'));

        $this->assertStringContainsString('## New candidates (0)', $body);
        $this->assertStringNotContainsString('- [ ]', $body);
    }

    public function test_history_section_is_included_when_log_has_decisions(): void
    {
        $log = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json')
            ->markPending('https://github.com/pending/one', 'extensions/_triage.yml')
            ->markAccepted('https://github.com/old/accepted')
            ->markRejected('https://github.com/old/rejected');

        $body = (new CandidateIssueRenderer())->render([], $log, new \DateTimeImmutable('2026-04-20T00:00:00Z'));

        $this->assertStringContainsString('## Previously decided (2)', $body);
        $this->assertStringContainsString('<details>', $body);
        $this->assertStringContainsString('✅ [old/accepted](https://github.com/old/accepted) accepted', $body);
        $this->assertStringContainsString('❌ [old/rejected](https://github.com/old/rejected) rejected', $body);
        // Pending entries never show in history.
        $this->assertStringNotContainsString('pending/one', $body);
    }

    private function repo(string $fullName, string $htmlUrl, ?string $description, int $stars): RepoSummary
    {
        return new RepoSummary(
            fullName:      $fullName,
            htmlUrl:       $htmlUrl,
            description:   $description,
            stars:         $stars,
            pushedAt:      '2026-04-01T00:00:00Z',
            createdAt:     '2024-01-01T00:00:00Z',
            archived:      false,
            fork:          false,
            licenseSpdx:   'MIT',
            defaultBranch: 'main',
        );
    }
}
