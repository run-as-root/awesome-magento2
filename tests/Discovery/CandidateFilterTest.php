<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateFilter;
use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\ExistingUrlsIndex;
use AwesomeList\Discovery\RepoSummary;
use PHPUnit\Framework\TestCase;

final class CandidateFilterTest extends TestCase
{
    private CandidateFilter $filter;
    private ExistingUrlsIndex $emptyIndex;
    private CandidateLog $emptyLog;

    protected function setUp(): void
    {
        $this->filter     = new CandidateFilter(new \DateTimeImmutable('2026-04-20T00:00:00Z'));
        $this->emptyIndex = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data/does-not-exist');
        $this->emptyLog   = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json');
    }

    public function test_healthy_repo_passes(): void
    {
        // 16 months old, 200 stars → velocity ~12.5/mo, clears the 2.0 threshold.
        $repo = $this->repo(['stars' => 200, 'pushedAt' => '2026-04-01T00:00:00Z', 'createdAt' => '2025-01-01T00:00:00Z']);
        $this->assertCount(1, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_archived_fails(): void
    {
        $repo = $this->repo(['archived' => true]);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_fork_fails(): void
    {
        $repo = $this->repo(['fork' => true]);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_no_license_fails(): void
    {
        $repo = $this->repo(['licenseSpdx' => null]);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_low_stars_fails(): void
    {
        $repo = $this->repo(['stars' => 5]);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_stale_pushed_at_fails(): void
    {
        $repo = $this->repo(['pushedAt' => '2023-01-01T00:00:00Z']);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_old_repo_with_low_velocity_fails(): void
    {
        // 5 years old, only 15 stars → velocity = 15/60 = 0.25 (< 2.0)
        $repo = $this->repo(['stars' => 15, 'pushedAt' => '2026-03-01T00:00:00Z', 'createdAt' => '2021-04-20T00:00:00Z']);
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_young_repo_with_low_stars_passes_velocity_exemption(): void
    {
        // 3 months old, 12 stars — velocity exempt
        $repo = $this->repo(['stars' => 12, 'pushedAt' => '2026-04-01T00:00:00Z', 'createdAt' => '2026-01-20T00:00:00Z']);
        $this->assertCount(1, $this->filter->filter([$repo], $this->emptyIndex, $this->emptyLog));
    }

    public function test_already_in_data_index_fails(): void
    {
        $repo  = $this->repo(['htmlUrl' => 'https://github.com/netz98/n98-magerun2']);
        $index = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data');
        $this->assertCount(0, $this->filter->filter([$repo], $index, $this->emptyLog));
    }

    public function test_already_in_log_fails(): void
    {
        $repo = $this->repo(['htmlUrl' => 'https://github.com/new/entry']);
        $log  = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json')
                    ->markRejected('https://github.com/new/entry');
        $this->assertCount(0, $this->filter->filter([$repo], $this->emptyIndex, $log));
    }

    public function test_pending_log_entry_is_rediscovered_so_metadata_stays_fresh(): void
    {
        $repo = $this->repo(['htmlUrl' => 'https://github.com/new/entry']);
        $log  = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/does-not-exist.json')
                    ->markPending('https://github.com/new/entry', 'extensions/_triage.yml');
        $this->assertCount(1, $this->filter->filter([$repo], $this->emptyIndex, $log));
    }

    /** @param array<string,mixed> $overrides */
    private function repo(array $overrides = []): RepoSummary
    {
        $defaults = [
            'fullName'      => 'owner/repo',
            'htmlUrl'       => 'https://github.com/owner/repo',
            'description'   => 'test',
            'stars'         => 50,
            'pushedAt'      => '2026-04-01T00:00:00Z',
            'createdAt'     => '2022-01-01T00:00:00Z',
            'archived'      => false,
            'fork'          => false,
            'licenseSpdx'   => 'MIT',
            'defaultBranch' => 'main',
        ];
        $v = array_merge($defaults, $overrides);
        return new RepoSummary(
            fullName:      $v['fullName'],
            htmlUrl:       $v['htmlUrl'],
            description:   $v['description'],
            stars:         $v['stars'],
            pushedAt:      $v['pushedAt'],
            createdAt:     $v['createdAt'],
            archived:      $v['archived'],
            fork:          $v['fork'],
            licenseSpdx:   $v['licenseSpdx'],
            defaultBranch: $v['defaultBranch'],
        );
    }
}
