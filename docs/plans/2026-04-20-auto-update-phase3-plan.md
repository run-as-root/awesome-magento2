# Auto-Update Phase 3 — Discovery Bot

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Weekly GitHub-wide discovery of new Magento 2 projects, surfaced as a single persistent "candidates" issue. Curator checks a box → automated PR appends the entry to the right YAML under `data/**/` with best-effort subcategory guess.

**Architecture:** Two CLIs + two workflows + one persistent JSON log.
`bin/discover.php` (Mondays 06:00 UTC) searches GitHub Search API + a list of known Magento orgs, applies quality-gate filters, dedupes against `data/**/*.yml` and `state/candidates.log.json`, upserts a singleton GitHub issue whose body contains one checkbox per candidate.
`bin/accept-candidate.php` (on issue edit) parses checked boxes, appends entries to the suggested YAML file in a new branch, opens a PR, and updates the log. Both CLIs use the built-in `GITHUB_TOKEN`; no new secrets.

**Tech Stack:** PHP 8.3+, Guzzle 7 (already installed), symfony/yaml (already installed), existing `Entry` / `YamlEntryLoader` / JSON schema. No new deps.

**References:**
- Design doc: `docs/plans/2026-04-19-auto-update-design.md` (Pipelines §, discover.yml + accept-candidate.yml)
- Phase 2/4a adapter-test pattern (`MockHandler` with recorded JSON fixtures).

**Out of scope:**
- Packagist search (GitHub search alone already produces high-quality signal; add Packagist if recall proves poor).
- Transitive dependency mining.
- Auto-merging accepted PRs.

---

## Task 1: `CandidateLog` — persistent dedup log

`state/candidates.log.json` records every URL ever surfaced as a candidate, plus its lifecycle status.

**Files:**
- Create: `lib/Discovery/CandidateLog.php`
- Create: `tests/Discovery/CandidateLogTest.php`
- Create: `state/candidates.log.json` (seed `{}` + newline)

### Step 1: Write failing test

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\CandidateLog;
use PHPUnit\Framework\TestCase;

final class CandidateLogTest extends TestCase
{
    public function test_missing_file_yields_empty_log(): void
    {
        $log = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/nope.json');
        $this->assertFalse($log->has('https://x'));
        $this->assertNull($log->statusOf('https://x'));
    }

    public function test_mark_transitions(): void
    {
        $log = CandidateLog::loadOrEmpty(__DIR__ . '/../fixtures/state/nope.json');
        $log = $log->markPending('https://github.com/a/b', 'extensions/_triage.yml');
        $this->assertSame('pending', $log->statusOf('https://github.com/a/b'));
        $log = $log->markAccepted('https://github.com/a/b');
        $this->assertSame('accepted', $log->statusOf('https://github.com/a/b'));
    }

    public function test_save_round_trips(): void
    {
        $path = tempnam(sys_get_temp_dir(), 'clog') . '.json';
        $log = CandidateLog::loadOrEmpty($path)
            ->markPending('https://github.com/a/b', 'extensions/search.yml')
            ->markRejected('https://github.com/c/d');
        $log->save($path);

        $reloaded = CandidateLog::loadOrEmpty($path);
        $this->assertSame('pending',  $reloaded->statusOf('https://github.com/a/b'));
        $this->assertSame('rejected', $reloaded->statusOf('https://github.com/c/d'));
        unlink($path);
    }
}
```

### Step 2: Implement `lib/Discovery/CandidateLog.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class CandidateLog
{
    private function __construct(private readonly array $byUrl) {}

    public static function loadOrEmpty(string $path): self
    {
        if (!is_file($path)) {
            return new self([]);
        }
        $data = json_decode((string) file_get_contents($path), true, flags: JSON_THROW_ON_ERROR);
        return new self(is_array($data) ? $data : []);
    }

    public function has(string $url): bool
    {
        return isset($this->byUrl[$url]);
    }

    public function statusOf(string $url): ?string
    {
        return $this->byUrl[$url]['status'] ?? null;
    }

    public function suggestedYaml(string $url): ?string
    {
        return $this->byUrl[$url]['suggested_yaml'] ?? null;
    }

    public function markPending(string $url, string $suggestedYaml): self
    {
        $byUrl = $this->byUrl;
        $byUrl[$url] = [
            'status'         => 'pending',
            'suggested_yaml' => $suggestedYaml,
            'discovered_at'  => $this->byUrl[$url]['discovered_at'] ?? gmdate('Y-m-d\TH:i:s\Z'),
        ];
        return new self($byUrl);
    }

    public function markAccepted(string $url): self
    {
        return $this->transition($url, 'accepted');
    }

    public function markRejected(string $url): self
    {
        return $this->transition($url, 'rejected');
    }

    public function save(string $path): void
    {
        $parent = dirname($path);
        if (!is_dir($parent)) {
            mkdir($parent, 0755, true);
        }
        file_put_contents($path, json_encode($this->byUrl, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
    }

    /** @return array<string, array<string, mixed>> */
    public function all(): array
    {
        return $this->byUrl;
    }

    private function transition(string $url, string $status): self
    {
        $byUrl = $this->byUrl;
        $byUrl[$url] = ($byUrl[$url] ?? []) + ['status' => $status, 'decided_at' => gmdate('Y-m-d\TH:i:s\Z')];
        $byUrl[$url]['status']     = $status;
        $byUrl[$url]['decided_at'] = gmdate('Y-m-d\TH:i:s\Z');
        return new self($byUrl);
    }
}
```

### Step 3: Seed file

Create `state/candidates.log.json` with exactly `{}\n`.

### Step 4: Verify + commit

```bash
vendor/bin/phpunit
git add lib/Discovery/CandidateLog.php tests/Discovery/CandidateLogTest.php state/candidates.log.json
git commit -m "feat: CandidateLog persistent dedup log for discovered urls"
```

---

## Task 2: `ExistingUrlsIndex` — dedup against data/

Given the `data/` directory, return every URL that already lives in a YAML. `DiscoveryScanner` uses this to skip already-curated entries.

**Files:**
- Create: `lib/Discovery/ExistingUrlsIndex.php`
- Create: `tests/Discovery/ExistingUrlsIndexTest.php`

### Step 1: Test

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\ExistingUrlsIndex;
use PHPUnit\Framework\TestCase;

final class ExistingUrlsIndexTest extends TestCase
{
    public function test_contains_urls_from_every_yaml_under_data(): void
    {
        $idx = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data');
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2'));
        $this->assertTrue($idx->contains('https://hyva.io/'));
        $this->assertFalse($idx->contains('https://github.com/ghost/never-heard-of-it'));
    }

    public function test_normalises_github_url_variants(): void
    {
        $idx = ExistingUrlsIndex::build(__DIR__ . '/../fixtures/enrichment/data');
        // Reuses the same normalisation logic GithubRepoAdapter::parseUrl uses for enrichment —
        // trailing slash, .git suffix, www., query string, path segments all collapse to the same key.
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2/'));
        $this->assertTrue($idx->contains('https://github.com/netz98/n98-magerun2.git'));
        $this->assertTrue($idx->contains('https://www.github.com/netz98/n98-magerun2'));
    }
}
```

### Step 2: Implementation

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Symfony\Component\Yaml\Yaml;

final class ExistingUrlsIndex
{
    private function __construct(private readonly array $urls) {}

    public static function build(string $dataDir): self
    {
        $urls = [];
        if (!is_dir($dataDir)) {
            return new self([]);
        }
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
        foreach ($it as $f) {
            if (!$f->isFile() || $f->getExtension() !== 'yml') {
                continue;
            }
            $rows = Yaml::parseFile($f->getPathname()) ?? [];
            foreach ($rows as $row) {
                if (!is_array($row) || empty($row['url'])) {
                    continue;
                }
                $urls[self::normalise((string) $row['url'])] = true;
            }
        }
        return new self($urls);
    }

    public function contains(string $url): bool
    {
        return isset($this->urls[self::normalise($url)]);
    }

    private static function normalise(string $url): string
    {
        $url = preg_replace('~[?#].*$~', '', $url) ?? $url;
        $url = preg_replace('~^(https?://)www\.~i', '$1', $url) ?? $url;
        $url = rtrim($url, '/');
        if (preg_match('~^(https?://github\.com/[^/]+/[^/]+?)(?:\.git)?(?:/.*)?$~', $url, $m)) {
            return strtolower($m[1]);
        }
        return strtolower($url);
    }
}
```

### Step 3: Commit

```bash
vendor/bin/phpunit
git add lib/Discovery/ExistingUrlsIndex.php tests/Discovery/ExistingUrlsIndexTest.php
git commit -m "feat: ExistingUrlsIndex dedupes against data/**/*.yml"
```

---

## Task 3: `RepoSummary` DTO + `GithubSearchClient`

Thin value object + HTTP client wrapping the GitHub Search + Org endpoints.

**Files:**
- Create: `lib/Discovery/RepoSummary.php`
- Create: `lib/Discovery/GithubSearchClient.php`
- Create: `tests/Discovery/GithubSearchClientTest.php`
- Create: `tests/fixtures/http/discovery/search-topic-magento2.json`
- Create: `tests/fixtures/http/discovery/org-repos.json`

### Step 1: Fixtures

`tests/fixtures/http/discovery/search-topic-magento2.json`:
```json
{
  "items": [
    {
      "full_name": "alpha/beta",
      "html_url": "https://github.com/alpha/beta",
      "description": "A Magento 2 module that does thing.",
      "stargazers_count": 42,
      "pushed_at": "2026-03-01T12:00:00Z",
      "created_at": "2024-01-01T00:00:00Z",
      "archived": false,
      "fork": false,
      "license": { "spdx_id": "MIT" },
      "default_branch": "main"
    },
    {
      "full_name": "someone/old",
      "html_url": "https://github.com/someone/old",
      "description": "Archived thing.",
      "stargazers_count": 200,
      "pushed_at": "2023-01-01T00:00:00Z",
      "created_at": "2018-01-01T00:00:00Z",
      "archived": true,
      "fork": false,
      "license": { "spdx_id": "MIT" },
      "default_branch": "main"
    }
  ]
}
```

`tests/fixtures/http/discovery/org-repos.json`:
```json
[
  {
    "full_name": "run-as-root/magento2-prometheus-exporter",
    "html_url": "https://github.com/run-as-root/magento2-prometheus-exporter",
    "description": "Prometheus exporter.",
    "stargazers_count": 60,
    "pushed_at": "2026-03-10T00:00:00Z",
    "created_at": "2021-01-01T00:00:00Z",
    "archived": false,
    "fork": false,
    "license": { "spdx_id": "MIT" },
    "default_branch": "main"
  }
]
```

### Step 2: `lib/Discovery/RepoSummary.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class RepoSummary
{
    public function __construct(
        public readonly string $fullName,
        public readonly string $htmlUrl,
        public readonly ?string $description,
        public readonly int $stars,
        public readonly ?string $pushedAt,
        public readonly ?string $createdAt,
        public readonly bool $archived,
        public readonly bool $fork,
        public readonly ?string $licenseSpdx,
        public readonly ?string $defaultBranch,
    ) {}

    public static function fromArray(array $row): self
    {
        return new self(
            fullName:      (string) ($row['full_name'] ?? ''),
            htmlUrl:       (string) ($row['html_url']  ?? ''),
            description:   $row['description'] ?? null,
            stars:         (int) ($row['stargazers_count'] ?? 0),
            pushedAt:      $row['pushed_at']  ?? null,
            createdAt:     $row['created_at'] ?? null,
            archived:      (bool) ($row['archived'] ?? false),
            fork:          (bool) ($row['fork'] ?? false),
            licenseSpdx:   $row['license']['spdx_id'] ?? null,
            defaultBranch: $row['default_branch'] ?? null,
        );
    }
}
```

### Step 3: `lib/Discovery/GithubSearchClient.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use GuzzleHttp\Client;

final class GithubSearchClient
{
    public function __construct(private readonly Client $http) {}

    /** @return RepoSummary[] */
    public function topicSearch(string $topic, int $minStars = 10): array
    {
        $response = $this->http->get('search/repositories', [
            'query' => ['q' => "topic:$topic stars:>=$minStars", 'per_page' => 50, 'sort' => 'updated'],
        ]);
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return array_map([RepoSummary::class, 'fromArray'], $body['items'] ?? []);
    }

    /** @return RepoSummary[] */
    public function orgRepos(string $org): array
    {
        $response = $this->http->get("orgs/$org/repos", ['query' => ['per_page' => 100, 'type' => 'sources']]);
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        return array_map([RepoSummary::class, 'fromArray'], $body ?? []);
    }
}
```

### Step 4: Test with MockHandler

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Discovery;

use AwesomeList\Discovery\GithubSearchClient;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GithubSearchClientTest extends TestCase
{
    public function test_topic_search_returns_repo_summaries(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/discovery/search-topic-magento2.json');
        $mock = new MockHandler([new Response(200, [], $body)]);
        $http = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);

        $client = new GithubSearchClient($http);
        $results = $client->topicSearch('magento2');

        $this->assertCount(2, $results);
        $this->assertSame('alpha/beta', $results[0]->fullName);
        $this->assertSame(42, $results[0]->stars);
        $this->assertTrue($results[1]->archived);
    }

    public function test_org_repos_returns_repo_summaries(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/discovery/org-repos.json');
        $mock = new MockHandler([new Response(200, [], $body)]);
        $http = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);

        $client = new GithubSearchClient($http);
        $results = $client->orgRepos('run-as-root');

        $this->assertCount(1, $results);
        $this->assertSame('run-as-root/magento2-prometheus-exporter', $results[0]->fullName);
    }
}
```

### Step 5: Commit

```bash
vendor/bin/phpunit
git add lib/Discovery/RepoSummary.php lib/Discovery/GithubSearchClient.php tests/Discovery/GithubSearchClientTest.php tests/fixtures/http/discovery/
git commit -m "feat: GithubSearchClient for topic search + org repo listing"
```

---

## Task 4: `CandidateFilter` — quality gates + dedup

**Files:**
- Create: `lib/Discovery/CandidateFilter.php`
- Create: `tests/Discovery/CandidateFilterTest.php`

### Step 1: Implementation rules (in order)

A `RepoSummary` passes iff:
- `!archived && !fork && licenseSpdx !== null`
- `stars >= 10`
- `pushedAt !== null && daysSince(pushedAt) <= 540` (18 months)
- velocity: if `createdAt` older than 6 months, `stars / ageMonths > 2`; else skip this check (young repo exemption).
- URL not in `ExistingUrlsIndex` (collapse via the same normalisation).
- URL not in `CandidateLog` (any status — accepted/rejected/pending all prevent re-surfacing).

### Step 2: Test (abbreviated — mirror GithubRepoAdapterTest patterns, cover active-pass, archived-fail, no-license-fail, already-in-data-fail, already-in-log-fail, young-low-stars-pass, old-low-velocity-fail)

### Step 3: Implementation

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

use DateTimeImmutable;

final class CandidateFilter
{
    private const MIN_STARS         = 10;
    private const MAX_STALE_DAYS    = 540;
    private const YOUNG_AGE_MONTHS  = 6;
    private const MIN_VELOCITY      = 2.0; // stars per month

    public function __construct(private readonly DateTimeImmutable $now) {}

    /**
     * @param RepoSummary[] $candidates
     * @return RepoSummary[]
     */
    public function filter(array $candidates, ExistingUrlsIndex $index, CandidateLog $log): array
    {
        return array_values(array_filter(
            $candidates,
            fn(RepoSummary $r): bool => $this->passes($r, $index, $log),
        ));
    }

    private function passes(RepoSummary $r, ExistingUrlsIndex $index, CandidateLog $log): bool
    {
        if ($r->archived || $r->fork || $r->licenseSpdx === null) {
            return false;
        }
        if ($r->stars < self::MIN_STARS) {
            return false;
        }
        if ($r->pushedAt === null || $this->daysSince($r->pushedAt) > self::MAX_STALE_DAYS) {
            return false;
        }
        if (!$this->meetsVelocity($r)) {
            return false;
        }
        if ($index->contains($r->htmlUrl)) {
            return false;
        }
        if ($log->has($r->htmlUrl)) {
            return false;
        }
        return true;
    }

    private function meetsVelocity(RepoSummary $r): bool
    {
        if ($r->createdAt === null) {
            return true;
        }
        $ageMonths = $this->monthsSince($r->createdAt);
        if ($ageMonths < self::YOUNG_AGE_MONTHS) {
            return true; // young repo exemption
        }
        return ($r->stars / max($ageMonths, 1)) > self::MIN_VELOCITY;
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }

    private function monthsSince(string $iso): float
    {
        return $this->daysSince($iso) / 30.0;
    }
}
```

### Step 4: Commit

```bash
vendor/bin/phpunit
git add lib/Discovery/CandidateFilter.php tests/Discovery/CandidateFilterTest.php
git commit -m "feat: CandidateFilter applies quality gates + dedup"
```

---

## Task 5: `CategoryGuesser`

Returns a suggested YAML path relative to `data/`. Defaults to `extensions/_triage.yml` when confidence is low.

**Files:**
- Create: `lib/Discovery/CategoryGuesser.php`
- Create: `tests/Discovery/CategoryGuesserTest.php`

### Heuristic (case-insensitive against description + full_name)

Keywords → file:

- `payment|paypal|stripe|adyen` → `extensions/payment.yml`
- `search|solr|elasticsearch|algolia` → `extensions/search.yml`
- `seo|marketing|newsletter` → `extensions/marketing.yml`
- `blog|cms|page|content` → `extensions/cms.yml`
- `admin|backend|grid` → `extensions/adminhtml.yml`
- `security|gdpr|captcha` → `extensions/security.yml`
- `deploy|ci|pipeline` → `extensions/deployment.yml`
- `docker|cache|redis|cron|infrastructure` → `extensions/infrastructure.yml`
- `language|locale|translation|i18n` → `extensions/localization.yml`
- `pwa|hyva|tailwind|alpine|react` → `extensions/pwa.yml`
- `cli|magerun|tool|debug|devtool|testing|phpstan` → `extensions/development-utilities.yml`
- otherwise → `extensions/_triage.yml`

### Implementation

```php
<?php declare(strict_types=1);
namespace AwesomeList\Discovery;

final class CategoryGuesser
{
    private const RULES = [
        'extensions/payment.yml'              => ['payment','paypal','stripe','adyen','checkout'],
        'extensions/search.yml'               => ['search','solr','elasticsearch','algolia','fulltext'],
        'extensions/marketing.yml'            => ['seo','marketing','newsletter','email','campaign'],
        'extensions/cms.yml'                  => ['blog','cms','page','content'],
        'extensions/adminhtml.yml'            => ['admin','backend','grid','adminhtml'],
        'extensions/security.yml'             => ['security','gdpr','captcha','vuln'],
        'extensions/deployment.yml'           => ['deploy','ci/cd','pipeline','deployer'],
        'extensions/infrastructure.yml'       => ['docker','cache','redis','cron','infrastructure','queue'],
        'extensions/localization.yml'         => ['language','locale','translation','i18n','language-pack'],
        'extensions/pwa.yml'                  => ['pwa','hyva','tailwind','alpine','react','vue','headless'],
        'extensions/development-utilities.yml' => ['cli','magerun','debug','devtool','testing','phpstan','phpunit','mock'],
    ];

    public function guess(RepoSummary $repo): string
    {
        $haystack = strtolower(trim(($repo->description ?? '') . ' ' . $repo->fullName));
        foreach (self::RULES as $file => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($haystack, $kw)) {
                    return $file;
                }
            }
        }
        return 'extensions/_triage.yml';
    }
}
```

### Tests: 5 cases covering multiple rules + triage fallback. Commit with `feat: CategoryGuesser suggests subcategory from description keywords`.

---

## Task 6: `DiscoveryScanner`

Runs topic searches + org scans, dedupes by `htmlUrl` across sources, applies filter + guesser, returns a list of `[RepoSummary, suggestedYaml]` pairs.

**Files:**
- Create: `lib/Discovery/DiscoveryScanner.php`
- Create: `tests/Discovery/DiscoveryScannerTest.php`

Constructor takes `GithubSearchClient`, `CandidateFilter`, `CategoryGuesser`. `scan(ExistingUrlsIndex $index, CandidateLog $log): array` produces pairs.

Search inputs:
- `topicSearch('magento2')`
- `topicSearch('magento-2')`
- `orgRepos(...)` for each of: run-as-root, elgentos, yireo, opengento, mage-os, hyva-themes, magepal

Implementation:

```php
public function scan(ExistingUrlsIndex $index, CandidateLog $log): array
{
    $byUrl = [];
    foreach (['magento2', 'magento-2'] as $topic) {
        foreach ($this->search->topicSearch($topic) as $repo) {
            $byUrl[$repo->htmlUrl] = $repo;
        }
    }
    foreach (self::KNOWN_ORGS as $org) {
        foreach ($this->search->orgRepos($org) as $repo) {
            $byUrl[$repo->htmlUrl] = $repo;
        }
    }
    $filtered = $this->filter->filter(array_values($byUrl), $index, $log);
    $out = [];
    foreach ($filtered as $repo) {
        $out[] = ['repo' => $repo, 'suggested_yaml' => $this->guesser->guess($repo)];
    }
    return $out;
}
```

Test: one stubbed GithubSearchClient (anon class) returning canned RepoSummary arrays, empty index, empty log. Assert scan produces 1-3 candidates with correct suggested_yaml.

Commit: `feat: DiscoveryScanner dedupes across searches and applies filter + guesser`.

---

## Task 7: `CandidateIssueRenderer`

Renders the issue body. Persistent singleton, identified by leading HTML comment `<!-- candidates-issue-v1 -->` (use this as the marker both for finding the issue and as a "you are editing the right issue" anchor).

**Files:**
- Create: `lib/Discovery/CandidateIssueRenderer.php`
- Create: `tests/Discovery/CandidateIssueRendererTest.php`

### Body layout

```
<!-- candidates-issue-v1 -->
# Magento 2 Discovery Candidates

_Weekly scan updated {run_at}. Check a box to auto-open a PR adding the entry to `data/`. Leave unchecked to reject (logged to `state/candidates.log.json`)._

## New candidates (N)

- [ ] [alpha/beta](https://github.com/alpha/beta) ★42 — A Magento 2 module that does thing. _(suggested: `extensions/_triage.yml`)_
- [ ] [run-as-root/magento2-prometheus-exporter](https://github.com/run-as-root/magento2-prometheus-exporter) ★60 — Prometheus exporter. _(suggested: `extensions/infrastructure.yml`)_

## Previously decided (M)

<details>
<summary>History</summary>

- ✅ [a/b](…) accepted 2026-04-12
- ❌ [c/d](…) rejected 2026-04-12

</details>
```

### Implementation

`render(array $candidates, CandidateLog $log, DateTimeImmutable $runAt): string` produces the full body. Include a history block listing URLs from the log whose status is not `pending`, most-recent first by `decided_at`.

Test: assert marker present, candidate count matches, both checkbox lines appear verbatim, history block present when log non-empty.

Commit: `feat: CandidateIssueRenderer composes the candidates issue body`.

---

## Task 8: `IssueUpserter`

Uses the GitHub REST API (via Guzzle) to find-or-create the singleton candidates issue and update its body.

**Files:**
- Create: `lib/Discovery/IssueUpserter.php`
- Create: `tests/Discovery/IssueUpserterTest.php`

### API calls

- `GET /repos/{owner}/{repo}/issues?state=open&labels=discovery-candidates&per_page=100`
- Among results, find one whose body contains `<!-- candidates-issue-v1 -->`.
- If found: `PATCH /repos/{owner}/{repo}/issues/{n}` with `{ title, body }`.
- Else: `POST /repos/{owner}/{repo}/issues` with `{ title, body, labels: ["discovery-candidates"] }`.

Constructor takes `Client $http` + `string $owner`, `string $repo`, `string $token`. Sets `Authorization: Bearer $token` header per call.

Method `upsert(string $title, string $body): array` returns the issue response.

### Test

Mock: list returns 1 matching issue → assert PATCH fired. Mock: list returns 0 matches → assert POST fired.

Commit: `feat: IssueUpserter manages the singleton candidates issue`.

---

## Task 9: `bin/discover.php`

CLI glue. Loads env vars (`GITHUB_TOKEN`, `GITHUB_REPOSITORY`), reads `data/` + `state/candidates.log.json`, runs the scan, updates the log (markPending for new), upserts the issue. Prints a summary.

```php
#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Discovery\CandidateFilter;
use AwesomeList\Discovery\CandidateIssueRenderer;
use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\CategoryGuesser;
use AwesomeList\Discovery\DiscoveryScanner;
use AwesomeList\Discovery\ExistingUrlsIndex;
use AwesomeList\Discovery\GithubSearchClient;
use AwesomeList\Discovery\IssueUpserter;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN') ?: null;
if ($token === null) {
    fwrite(STDERR, "GITHUB_TOKEN required\n");
    exit(1);
}
$repo = getenv('GITHUB_REPOSITORY') ?: 'run-as-root/awesome-magento2';
[$owner, $name] = explode('/', $repo, 2);

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 30,
    'headers'  => [
        'Authorization' => "Bearer $token",
        'Accept'        => 'application/vnd.github+json',
        'User-Agent'    => 'awesome-magento2-discovery',
    ],
]);
$now = new DateTimeImmutable();

$index = ExistingUrlsIndex::build(__DIR__ . '/../data');
$logPath = __DIR__ . '/../state/candidates.log.json';
$log = CandidateLog::loadOrEmpty($logPath);

$scanner = new DiscoveryScanner(
    new GithubSearchClient($http),
    new CandidateFilter($now),
    new CategoryGuesser(),
);
$candidates = $scanner->scan($index, $log);

foreach ($candidates as $c) {
    $log = $log->markPending($c['repo']->htmlUrl, $c['suggested_yaml']);
}
$log->save($logPath);

$body = (new CandidateIssueRenderer())->render($candidates, $log, $now);
$title = 'Magento 2 Discovery Candidates';
(new IssueUpserter($http, $owner, $name, $token))->upsert($title, $body);

echo "Discovered " . count($candidates) . " new candidates; log updated.\n";
```

Add to `composer.json` scripts:
```json
"discover": "php bin/discover.php"
```

Commit: `feat: bin/discover.php runs the weekly scan + upserts candidates issue`.

---

## Task 10: `.github/workflows/discover.yml`

```yaml
name: Discover candidates

on:
  schedule:
    - cron: '0 6 * * 1'   # Mondays 06:00 UTC
  workflow_dispatch:

concurrency:
  group: discover
  cancel-in-progress: false

jobs:
  discover:
    runs-on: ubuntu-latest
    permissions:
      contents: write
      issues: write
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: none
          tools: composer:v2
      - run: composer install --no-interaction --no-progress
      - run: composer discover
        env:
          GITHUB_TOKEN:      ${{ secrets.GITHUB_TOKEN }}
          GITHUB_REPOSITORY: ${{ github.repository }}
      - name: Commit candidates log
        run: |
          if git diff --quiet state/candidates.log.json; then
            echo "No log changes"; exit 0
          fi
          git config user.name  'github-actions[bot]'
          git config user.email 'github-actions[bot]@users.noreply.github.com'
          git add state/candidates.log.json
          git commit -m 'chore: refresh candidates log'
          git pull --rebase origin master
          git push
```

Commit: `ci: weekly discover.yml workflow`.

---

## Task 11: `CandidateParser`

Parses a candidates-issue body and returns the structured state of each checkbox: `[url, suggested_yaml, checked]`.

**Files:**
- Create: `lib/Discovery/CandidateParser.php`
- Create: `tests/Discovery/CandidateParserTest.php`

### Implementation

Look for lines matching:
```
- [(x| )] [name](url) ★N — description _(suggested: `path/to.yml`)_
```

Regex: `~^- \[([x ])\] \[([^\]]+)\]\(([^)]+)\).*?_\(suggested: `([^`]+)`\)_~m`

Return entries with `url`, `suggestedYaml`, `checked` (bool).

Skip lines outside the "New candidates" section (i.e. any line following `## Previously decided`). Simplest: scan until we hit `## Previously decided` heading.

Tests: 4-box body with 2 checked, 1 unchecked, 1 outside the history section → expect 2 checked + 1 unchecked as output.

Commit: `feat: CandidateParser reads checkbox state from issue body`.

---

## Task 12: `EntryAppender`

Given a path relative to `data/` and a new entry spec, appends the YAML block to the file. Preserves existing entries + whitespace.

**Files:**
- Create: `lib/Discovery/EntryAppender.php`
- Create: `tests/Discovery/EntryAppenderTest.php`

### Implementation

Build the YAML string by hand (easier to control formatting than round-tripping through symfony/yaml, which reformats):

```php
public function append(string $filePath, array $entry): void
{
    $yaml = "- name: {$entry['name']}\n";
    $yaml .= "  url: {$entry['url']}\n";
    $yaml .= "  description: {$this->escape($entry['description'])}\n";
    $yaml .= "  type: {$entry['type']}\n";
    $yaml .= "  added: \"{$entry['added']}\"\n";

    if (!is_file($filePath)) {
        file_put_contents($filePath, $yaml);
        return;
    }
    $current = file_get_contents($filePath);
    if (!str_ends_with($current, "\n")) {
        $current .= "\n";
    }
    file_put_contents($filePath, $current . $yaml);
}

private function escape(string $s): string
{
    // Descriptions with colon or special chars get double-quoted.
    if (preg_match('/[:#&*!|>\'"%@`]/', $s)) {
        return '"' . addcslashes($s, '"\\') . '"';
    }
    return $s;
}
```

Test: new file + append-to-existing + schema validation stays green after append.

Commit: `feat: EntryAppender writes a YAML entry to a data file`.

---

## Task 13: `bin/accept-candidate.php`

Reads the current candidates issue body (passed in via env `ISSUE_BODY` — GitHub Actions provides `${{ github.event.issue.body }}`), processes newly-checked boxes:

1. Parse the body → list of `{url, suggestedYaml, checked}`.
2. For each `checked` URL where `CandidateLog::statusOf($url) !== 'accepted'`:
   - Fetch repo details (name, description — reuse `GithubSearchClient::topicSearch` won't help; use a small `repos/{owner}/{repo}` fetch). Actually simpler: the issue body already contains `name` in the link text. Use that + fetch description from GitHub for accuracy.
   - Append entry to `data/{suggestedYaml}` via `EntryAppender` with `type: github_repo`, `added: <today>`.
   - Update `CandidateLog`: markAccepted.
3. For each `unchecked` URL where log status is `pending`: markRejected.
4. Save log.
5. If any files changed: `git checkout -b candidates/YYYY-MM-DD-HHMM`, commit, push, open PR via `GITHUB_TOKEN` → `POST /repos/{owner}/{repo}/pulls`.

Keep it simple — process ALL currently-checked boxes on every invocation; idempotency comes from `markAccepted` gating.

```php
#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Discovery\CandidateLog;
use AwesomeList\Discovery\CandidateParser;
use AwesomeList\Discovery\EntryAppender;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN');
$repo  = getenv('GITHUB_REPOSITORY') ?: 'run-as-root/awesome-magento2';
[$owner, $name] = explode('/', $repo, 2);
$body = getenv('ISSUE_BODY') ?: '';
if ($body === '' || $token === false) {
    fwrite(STDERR, "ISSUE_BODY + GITHUB_TOKEN required\n");
    exit(1);
}

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 30,
    'headers'  => [
        'Authorization' => "Bearer $token",
        'Accept'        => 'application/vnd.github+json',
        'User-Agent'    => 'awesome-magento2-accept-candidate',
    ],
]);

$parser = new CandidateParser();
$parsed = $parser->parse($body);

$logPath = __DIR__ . '/../state/candidates.log.json';
$log = CandidateLog::loadOrEmpty($logPath);
$appender = new EntryAppender();
$today = (new DateTimeImmutable())->format('Y-m-d');

$accepted = [];
foreach ($parsed as $row) {
    if (!$row['checked']) {
        if ($log->statusOf($row['url']) === 'pending') {
            $log = $log->markRejected($row['url']);
        }
        continue;
    }
    if ($log->statusOf($row['url']) === 'accepted') {
        continue;
    }
    // Fetch details for nicer description
    $path = parse_url($row['url'], PHP_URL_PATH) ?? '';
    $description = $row['url'];
    try {
        $resp = $http->get("repos" . $path);
        $meta = json_decode((string) $resp->getBody(), true, flags: JSON_THROW_ON_ERROR);
        $description = (string) ($meta['description'] ?? $row['url']);
    } catch (\Throwable $e) {
        // fall back to url
    }
    $entry = [
        'name'        => basename($path),
        'url'         => $row['url'],
        'description' => $description,
        'type'        => 'github_repo',
        'added'       => $today,
    ];
    $target = __DIR__ . '/../data/' . $row['suggested_yaml'];
    if (!is_dir(dirname($target))) {
        mkdir(dirname($target), 0755, true);
    }
    $appender->append($target, $entry);
    $log = $log->markAccepted($row['url']);
    $accepted[] = $entry;
}
$log->save($logPath);

echo "Processed " . count($accepted) . " accepted candidates.\n";
```

Add to `composer.json` scripts:
```json
"accept-candidate": "php bin/accept-candidate.php"
```

Note: the workflow (Task 14) handles the git branch + commit + push + PR creation. Keeping those out of the PHP script keeps the script purely about data mutation; git ops live in bash.

Commit: `feat: bin/accept-candidate.php appends accepted candidates to data/**`.

---

## Task 14: `.github/workflows/accept-candidate.yml`

```yaml
name: Accept candidate

on:
  issues:
    types: [edited]

jobs:
  accept:
    if: contains(github.event.issue.body, '<!-- candidates-issue-v1 -->')
    runs-on: ubuntu-latest
    permissions:
      contents: write
      issues: write
      pull-requests: write
    steps:
      - uses: actions/checkout@v4
      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.3'
          coverage: none
          tools: composer:v2
      - run: composer install --no-interaction --no-progress
      - run: composer accept-candidate
        env:
          GITHUB_TOKEN:      ${{ secrets.GITHUB_TOKEN }}
          GITHUB_REPOSITORY: ${{ github.repository }}
          ISSUE_BODY:        ${{ github.event.issue.body }}
      - name: Validate + open PR
        run: |
          composer validate-data
          if git diff --quiet data state/candidates.log.json; then
            echo "No new candidates to PR"; exit 0
          fi
          BRANCH="candidates/$(date -u +%Y-%m-%d-%H%M)"
          git config user.name  'github-actions[bot]'
          git config user.email 'github-actions[bot]@users.noreply.github.com'
          git checkout -b "$BRANCH"
          git add data state/candidates.log.json
          git commit -m "feat: accept candidates from issue #${{ github.event.issue.number }}"
          git push origin "$BRANCH"
          gh pr create \
            --base master \
            --head "$BRANCH" \
            --title "Accept candidates from #${{ github.event.issue.number }}" \
            --body "Auto-generated from weekly discovery candidates issue #${{ github.event.issue.number }}."
        env:
          GH_TOKEN: ${{ secrets.GITHUB_TOKEN }}
```

Commit: `ci: accept-candidate.yml opens PR when candidate checkboxes are ticked`.

---

## Task 15: Docs

Update `CLAUDE.md` under "Content architecture":

> - Discovery pipeline: `discover.yml` (Mondays 06:00 UTC) scans GitHub for Magento-related repos, filters by quality gates, and upserts the singleton "Magento 2 Discovery Candidates" issue with checkboxes per candidate. Checking a box fires `accept-candidate.yml`, which appends the entry to the best-fit `data/extensions/<subcategory>.yml` (or `extensions/_triage.yml` for low-confidence matches) and opens a PR. State lives in `state/candidates.log.json` — bot-maintained dedup log.

Update `contributing.md` with a "Maintainer workflow" section describing the weekly triage habit.

Commit: `docs: document Phase 3 discovery bot`.

---

## Task 16: End-to-end + merge

### Step 1: Local dry run

```bash
GITHUB_TOKEN=<your-token> GITHUB_REPOSITORY=run-as-root/awesome-magento2 composer discover
```

Expected: scans complete in ≤30s, `state/candidates.log.json` populated with N pending URLs, a real issue appears (or updates) on GitHub.

### Step 2: Smoke test accept-candidate locally

```bash
ISSUE_BODY="$(cat tests/fixtures/discovery/issue-body-sample.md)" GITHUB_TOKEN=<token> composer accept-candidate
```

Fixture body should have 1 checkbox pre-ticked; expect an entry appended to the suggested YAML and log updated.

### Step 3: Full suite + schema

```bash
vendor/bin/phpunit && composer validate-data
```

All green.

### Step 4: Merge

```bash
git checkout master && git merge --ff-only feature/phase3-discovery && git push
```

### Step 5: Trigger discover workflow manually once

```bash
gh workflow run discover.yml --ref master
```

Verify: issue appears, log committed, no errors.

---

## Phase 4+ preview

- Phase 4c: revisit the YouTube `channel` vs `playlist` URL-type split if real signal quality suggests splitting `youtube_playlist` into `youtube_channel`.
- actions/checkout@v4 → v5 (deprecation by Sep 2026).
