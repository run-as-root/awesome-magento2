<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

final class GithubRepoAdapter implements EnrichmentAdapter
{
    private const ACTIVE_COMMIT_DAYS  = 90;
    private const ACTIVE_RELEASE_DAYS = 365;
    private const GRAVEYARD_DAYS      = 365 * 3;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'github_repo';
    }

    public function enrich(Entry $entry): EnrichmentResult
    {
        [$owner, $repo] = $this->parseUrl($entry->url ?? '');
        $repoData       = $this->getJson("repos/$owner/$repo");
        $releaseData    = $this->getJsonOrNull("repos/$owner/$repo/releases/latest");

        $lastCommit  = $repoData['pushed_at'] ?? null;
        $lastRelease = $releaseData['published_at'] ?? null;
        $archived    = (bool) ($repoData['archived'] ?? false);
        $stars       = (int) ($repoData['stargazers_count'] ?? 0);
        $fork        = (bool) ($repoData['fork'] ?? false);

        $activelyMaintained = $this->isActive($lastCommit, $lastRelease);
        $graveyard          = $this->isGraveyard($archived, $lastCommit, $lastRelease);

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $activelyMaintained,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: [
                'github' => [
                    'stars'        => $stars,
                    'last_commit'  => $lastCommit,
                    'last_release' => $lastRelease,
                    'archived'     => $archived,
                    'fork'         => $fork,
                ],
            ],
        );
    }

    private function parseUrl(string $url): array
    {
        // Strip query + fragment, optional .git suffix, optional www., optional trailing path segments.
        $cleaned = preg_replace('~[?#].*$~', '', $url) ?? $url;
        if (!preg_match('~^https?://(?:www\.)?github\.com/([^/]+)/([^/]+?)(?:\.git)?(?:/.*)?$~', $cleaned, $m)) {
            throw new RuntimeException("GithubRepoAdapter cannot parse url: $url");
        }
        return [$m[1], $m[2]];
    }

    private function getJson(string $path): array
    {
        $response = $this->http->get($path, ['headers' => ['Accept' => 'application/vnd.github+json']]);
        return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
    }

    private function getJsonOrNull(string $path): ?array
    {
        try {
            return $this->getJson($path);
        } catch (ClientException $e) {
            return $e->getCode() === 404 ? null : throw $e;
        }
    }

    /**
     * Relaxed from design doc's "≥3 releases / 12mo" to "any release ≤365d" —
     * /releases/latest is one call; counting releases requires pagination.
     * Revisit in Phase 4a if false-positive rate is high.
     */
    private function isActive(?string $lastCommit, ?string $lastRelease): bool
    {
        if ($lastCommit === null || $lastRelease === null) {
            return false;
        }
        return $this->daysSince($lastCommit) <= self::ACTIVE_COMMIT_DAYS
            && $this->daysSince($lastRelease) <= self::ACTIVE_RELEASE_DAYS;
    }

    private function isGraveyard(bool $archived, ?string $lastCommit, ?string $lastRelease): bool
    {
        if ($archived) {
            return true;
        }
        $commitStale  = $lastCommit === null || $this->daysSince($lastCommit) > self::GRAVEYARD_DAYS;
        $releaseStale = $lastRelease === null || $this->daysSince($lastRelease) > self::GRAVEYARD_DAYS;
        return $commitStale && $releaseStale;
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        return (int) $this->now->diff($then)->days;
    }
}
