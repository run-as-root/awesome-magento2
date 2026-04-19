# Auto-Update Phase 2 — Enrichment Core (github_repo)

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Automatically enrich every `github_repo` entry with freshness signals from the GitHub API, write them to `state/enrichment.json`, and let the renderer surface 🔥/🫡 badges plus a graveyard section. Other adapter types land in Phase 4a.

**Architecture:** Pluggable `EnrichmentAdapter` interface, `AdapterFactory` keyed by `EntryType`, a lone `GithubRepoAdapter` in Phase 2. `Enricher` orchestrates: iterates every entry across `data/**/*.yml`, dispatches the matching adapter (or skips if none), merges results into the sidecar, then hands the collected state to a `VitalityRanker` that computes per-category top-10% `vitality_hot` flags. A new GitHub Action runs the pipeline nightly and commits `state/enrichment.json` on change. The Phase 1 `YamlEntryList` parser is extended to split graveyard entries into a `<details>` appendix.

**Tech Stack:** PHP 8.1+, `guzzlehttp/guzzle:^7` (brings a `MockHandler` that makes adapter tests deterministic), existing PHPUnit + symfony/yaml.

**References:**
- Design doc: `docs/plans/2026-04-19-auto-update-design.md` (adapter catalog §, retirement thresholds §, sidecar schema §)
- Phase 1 plan: `docs/plans/2026-04-19-auto-update-phase1-plan.md`
- Prior art: [#108 — 🔥/🫡 vitality indicators](https://github.com/run-as-root/awesome-magento2/issues/108)

**Out of scope** (Phase 3+ plans):
- Adapters for blog / packagist_pkg / event / youtube_playlist / course / vendor_site / canonical / archive
- Link-liveness (404) tracking and the 90-day hard-delete rule
- Discovery bot and candidates issue (Phase 3)
- iCal/JSON event feeds (Phase 5)
- Resurrecting entries whose signals recover (manual for now)
- Migrating sections other than Frontends (Phase 4b)

---

## Task 0: Add `guzzlehttp/guzzle`

Guzzle is the standard PHP HTTP client and ships `GuzzleHttp\Handler\MockHandler`, which lets adapter tests replay canned JSON without hitting the real GitHub API.

**Files:**
- Modify: `composer.json`
- Modify: `composer.lock`

**Step 1: Add the dependency**

Run: `composer require guzzlehttp/guzzle:^7`
Expected: installs Guzzle + transitive deps (`psr/http-*`, `guzzlehttp/psr7`, `guzzlehttp/promises`).

**Step 2: Verify the lock file is staged**

Run: `git status` — `composer.json` + `composer.lock` modified, nothing else.

**Step 3: Smoke-test the autoload**

Run: `php -r 'require "vendor/autoload.php"; var_dump(class_exists(GuzzleHttp\Client::class));'`
Expected: `bool(true)`.

**Step 4: Run full suite, confirm Phase 1 still green**

Run: `vendor/bin/phpunit && composer validate-data`
Expected: 20 tests green, schema validates.

**Step 5: Commit**

```bash
git add composer.json composer.lock
git commit -m "chore: add guzzle for enrichment http calls"
```

---

## Task 1: `EnrichmentResult` value object

A small DTO returned by every adapter. Holds the timestamp of the check plus a flat `signals` hash and an optional `github` block (other adapter types will add their own blocks later). Matches the sidecar JSON shape exactly so the `Enricher` can serialize it with no transformation.

**Files:**
- Create: `lib/Enrichment/EnrichmentResult.php`
- Create: `tests/Enrichment/EnrichmentResultTest.php`

**Step 1: Write the failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\EnrichmentResult;
use PHPUnit\Framework\TestCase;

final class EnrichmentResultTest extends TestCase
{
    public function test_it_serialises_to_sidecar_shape(): void
    {
        $result = new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['actively_maintained' => true, 'graveyard_candidate' => false],
            typeData: ['github' => ['stars' => 42, 'archived' => false]],
        );

        $this->assertSame([
            'last_checked' => '2026-04-19T02:00:00Z',
            'github'       => ['stars' => 42, 'archived' => false],
            'signals'      => ['actively_maintained' => true, 'graveyard_candidate' => false],
        ], $result->toArray());
    }

    public function test_it_omits_type_data_when_empty(): void
    {
        $result = new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['vitality_hot' => false],
            typeData: [],
        );

        $this->assertSame([
            'last_checked' => '2026-04-19T02:00:00Z',
            'signals'      => ['vitality_hot' => false],
        ], $result->toArray());
    }
}
```

**Step 2: Verify failure**

Run: `vendor/bin/phpunit tests/Enrichment/EnrichmentResultTest.php`
Expected: `Class "AwesomeList\Enrichment\EnrichmentResult" not found`.

**Step 3: Implement `lib/Enrichment/EnrichmentResult.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

final class EnrichmentResult
{
    public function __construct(
        public readonly string $lastChecked,
        public readonly array $signals,
        public readonly array $typeData = [],
    ) {}

    public function toArray(): array
    {
        $out = ['last_checked' => $this->lastChecked];
        foreach ($this->typeData as $key => $block) {
            $out[$key] = $block;
        }
        $out['signals'] = $this->signals;
        return $out;
    }
}
```

**Step 4: Verify pass**

Run: `vendor/bin/phpunit tests/Enrichment/EnrichmentResultTest.php`
Expected: 2 tests, OK.

**Step 5: Commit**

```bash
git add lib/Enrichment/EnrichmentResult.php tests/Enrichment/EnrichmentResultTest.php
git commit -m "feat: EnrichmentResult DTO matching sidecar json shape"
```

---

## Task 2: `EnrichmentAdapter` interface

Defines the contract every type-specific adapter implements.

**Files:**
- Create: `lib/Enrichment/EnrichmentAdapter.php`

**Step 1: Implement**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;

interface EnrichmentAdapter
{
    /** Returns the `EntryType` string value this adapter handles (e.g. 'github_repo'). */
    public function type(): string;

    /** Fetches fresh signals for the entry. Implementations must be deterministic in tests. */
    public function enrich(Entry $entry): EnrichmentResult;
}
```

No test needed — pure interface, covered transitively by the GitHub adapter test.

**Step 2: Commit**

```bash
git add lib/Enrichment/EnrichmentAdapter.php
git commit -m "feat: EnrichmentAdapter interface"
```

---

## Task 3: `GithubRepoAdapter` with recorded HTTP fixtures

The first real adapter. Calls two GitHub endpoints per entry (`GET /repos/:owner/:repo` and `GET /repos/:owner/:repo/releases/latest`), folds the responses into an `EnrichmentResult`. Uses Guzzle's `MockHandler` in tests so no real network traffic.

Signals produced (per design doc adapter catalog):
- `actively_maintained`: `true` iff last commit ≤ 90 days old AND a release exists ≤ 365 days old
- `graveyard_candidate`: `true` iff repo is archived OR (last commit > 3 years AND last release > 3 years OR no release at all)
- `vitality_hot`: left `false` here — the `VitalityRanker` (Task 5) sets it based on cross-entry ranking

Type-data block (`github:`):
- `stars`, `last_commit`, `last_release`, `archived`, `fork`

**Files:**
- Create: `lib/Enrichment/GithubRepoAdapter.php`
- Create: `tests/Enrichment/GithubRepoAdapterTest.php`
- Create: `tests/fixtures/http/github/repos-active.json`
- Create: `tests/fixtures/http/github/releases-active.json`
- Create: `tests/fixtures/http/github/repos-archived.json`
- Create: `tests/fixtures/http/github/repos-stale.json`
- Create: `tests/fixtures/http/github/releases-stale.json`
- Create: `tests/fixtures/http/github/releases-404.txt` *(empty — simulates no-release response)*

**Step 1: Build the three repo fixtures**

`tests/fixtures/http/github/repos-active.json` — a recent, healthy repo:

```json
{
  "name": "n98-magerun2",
  "full_name": "netz98/n98-magerun2",
  "html_url": "https://github.com/netz98/n98-magerun2",
  "archived": false,
  "fork": false,
  "stargazers_count": 2147,
  "pushed_at": "2026-04-15T09:23:00Z"
}
```

`tests/fixtures/http/github/releases-active.json`:

```json
{
  "tag_name": "v9.0.1",
  "published_at": "2026-03-28T00:00:00Z"
}
```

`tests/fixtures/http/github/repos-archived.json` — archived → graveyard:

```json
{
  "name": "abandoned-thing",
  "full_name": "someone/abandoned-thing",
  "html_url": "https://github.com/someone/abandoned-thing",
  "archived": true,
  "fork": false,
  "stargazers_count": 30,
  "pushed_at": "2023-01-01T00:00:00Z"
}
```

`tests/fixtures/http/github/repos-stale.json` — no activity in 3+ years, no releases:

```json
{
  "name": "stale-repo",
  "full_name": "org/stale-repo",
  "html_url": "https://github.com/org/stale-repo",
  "archived": false,
  "fork": false,
  "stargazers_count": 12,
  "pushed_at": "2022-01-01T00:00:00Z"
}
```

`tests/fixtures/http/github/releases-stale.json` (empty `[]`-ish response shape returned by `/releases/latest` when none exist — GitHub returns 404, we simulate that via an HTTP error in the test, not a JSON body). Skip this file — we'll feed a 404 response directly in the test.

**Step 2: Write the failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class GithubRepoAdapterTest extends TestCase
{
    public function test_it_reports_active_maintenance_for_recent_repo(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-active.json')),
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/releases-active.json')),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/netz98/n98-magerun2'));

        $this->assertSame('2026-04-19T02:00:00Z', $result->lastChecked);
        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(2147, $result->typeData['github']['stars']);
        $this->assertSame('2026-04-15T09:23:00Z', $result->typeData['github']['last_commit']);
        $this->assertSame('2026-03-28T00:00:00Z', $result->typeData['github']['last_release']);
    }

    public function test_archived_repo_is_a_graveyard_candidate(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-archived.json')),
            new Response(404),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/someone/abandoned-thing'));

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->typeData['github']['archived']);
        $this->assertNull($result->typeData['github']['last_release']);
    }

    public function test_stale_repo_without_release_is_graveyard_but_not_archived(): void
    {
        $now     = new \DateTimeImmutable('2026-04-19T02:00:00Z');
        $adapter = $this->buildAdapter([
            new Response(200, [], (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-stale.json')),
            new Response(404),
        ], $now);

        $result = $adapter->enrich($this->entry('https://github.com/org/stale-repo'));

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertFalse($result->typeData['github']['archived']);
    }

    public function test_type_returns_github_repo(): void
    {
        $this->assertSame('github_repo', (new GithubRepoAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function buildAdapter(array $queuedResponses, \DateTimeImmutable $now): GithubRepoAdapter
    {
        $mock   = new MockHandler($queuedResponses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);
        return new GithubRepoAdapter($client, $now);
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test',
            url: $url,
            description: null,
            type: EntryType::GithubRepo,
            added: '2020-01-01',
        );
    }
}
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/Enrichment/GithubRepoAdapterTest.php`
Expected: `Class "AwesomeList\Enrichment\GithubRepoAdapter" not found`.

**Step 4: Implement `lib/Enrichment/GithubRepoAdapter.php`**

```php
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
        if (!preg_match('~^https?://github\.com/([^/]+)/([^/]+?)/?$~', $url, $m)) {
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
```

**Step 5: Verify pass**

Run: `vendor/bin/phpunit tests/Enrichment/GithubRepoAdapterTest.php`
Expected: 4 tests, OK.

Run full suite: `vendor/bin/phpunit`
Expected: 26 tests, green.

**Step 6: Commit**

```bash
git add lib/Enrichment/GithubRepoAdapter.php tests/Enrichment/GithubRepoAdapterTest.php tests/fixtures/http/
git commit -m "feat: GithubRepoAdapter enriches github_repo entries"
```

---

## Task 4: `AdapterFactory`

Registry pattern. Looks up an adapter by `EntryType` value, returns `null` for types with no Phase 2 adapter (everything except `github_repo`). Mirrors `ParserFactory`.

**Files:**
- Create: `lib/Enrichment/AdapterFactory.php`
- Create: `tests/Enrichment/AdapterFactoryTest.php`

**Step 1: Write the failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use PHPUnit\Framework\TestCase;

final class AdapterFactoryTest extends TestCase
{
    public function test_it_returns_github_adapter_for_github_repo_type(): void
    {
        $factory = new AdapterFactory([new GithubRepoAdapter(new Client(), new \DateTimeImmutable())]);
        $this->assertInstanceOf(GithubRepoAdapter::class, $factory->for(EntryType::GithubRepo));
    }

    public function test_it_returns_null_for_unsupported_type(): void
    {
        $factory = new AdapterFactory([new GithubRepoAdapter(new Client(), new \DateTimeImmutable())]);
        $this->assertNull($factory->for(EntryType::Blog));
    }
}
```

**Step 2: Verify failure**

Run: `vendor/bin/phpunit tests/Enrichment/AdapterFactoryTest.php`
Expected: `Class "AwesomeList\Enrichment\AdapterFactory" not found`.

**Step 3: Implement `lib/Enrichment/AdapterFactory.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\EntryType;

final class AdapterFactory
{
    /** @var array<string, EnrichmentAdapter> */
    private readonly array $byType;

    /** @param EnrichmentAdapter[] $adapters */
    public function __construct(array $adapters)
    {
        $map = [];
        foreach ($adapters as $adapter) {
            $map[$adapter->type()] = $adapter;
        }
        $this->byType = $map;
    }

    public function for(EntryType $type): ?EnrichmentAdapter
    {
        return $this->byType[$type->value] ?? null;
    }
}
```

**Step 4: Verify pass + full suite**

Run: `vendor/bin/phpunit`
Expected: 28 tests, OK.

**Step 5: Commit**

```bash
git add lib/Enrichment/AdapterFactory.php tests/Enrichment/AdapterFactoryTest.php
git commit -m "feat: AdapterFactory resolves EnrichmentAdapter by EntryType"
```

---

## Task 5: `VitalityRanker`

Post-processes the collected enrichment results to mark the top 10% of `github_repo` entries (per category) as `vitality_hot: true`, using star count as the score. Categories with fewer than 5 entries get no hot badges — otherwise every repo in a 2-entry category is "top 50%", which is meaningless.

Category = the basename of the YAML file the entry came from (e.g. `frontends` for `data/frontends.yml`).

**Files:**
- Create: `lib/Enrichment/VitalityRanker.php`
- Create: `tests/Enrichment/VitalityRankerTest.php`

**Step 1: Write the failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\EnrichmentResult;
use AwesomeList\Enrichment\VitalityRanker;
use PHPUnit\Framework\TestCase;

final class VitalityRankerTest extends TestCase
{
    public function test_it_marks_top_decile_when_category_has_enough_entries(): void
    {
        $results = [];
        for ($i = 1; $i <= 10; $i++) {
            $results["url-$i"] = [
                'category' => 'tools',
                'result'   => $this->withStars($i * 100),
            ];
        }

        $ranked = (new VitalityRanker())->rank($results);

        // Top 10% of 10 = 1 entry → the 1000-star one (url-10) is hot.
        $this->assertTrue($ranked['url-10']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['url-9']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['url-1']['result']->signals['vitality_hot']);
    }

    public function test_it_marks_nothing_when_category_is_too_small(): void
    {
        $results = [
            'a' => ['category' => 'tiny', 'result' => $this->withStars(5000)],
            'b' => ['category' => 'tiny', 'result' => $this->withStars(10)],
        ];

        $ranked = (new VitalityRanker())->rank($results);

        $this->assertFalse($ranked['a']['result']->signals['vitality_hot']);
        $this->assertFalse($ranked['b']['result']->signals['vitality_hot']);
    }

    public function test_it_ignores_non_github_results(): void
    {
        $results = [
            'gh'   => ['category' => 'mixed', 'result' => $this->withStars(9999)],
            'blog' => ['category' => 'mixed', 'result' => new EnrichmentResult('2026-01-01T00:00:00Z', ['vitality_hot' => false])],
        ];

        $ranked = (new VitalityRanker())->rank($results);

        $this->assertFalse($ranked['gh']['result']->signals['vitality_hot']); // 1-entry category after filtering, under threshold
        $this->assertArrayNotHasKey('github', $ranked['blog']['result']->typeData);
    }

    private function withStars(int $stars): EnrichmentResult
    {
        return new EnrichmentResult(
            lastChecked: '2026-04-19T02:00:00Z',
            signals: ['vitality_hot' => false, 'actively_maintained' => true, 'graveyard_candidate' => false],
            typeData: ['github' => ['stars' => $stars, 'last_commit' => null, 'last_release' => null, 'archived' => false, 'fork' => false]],
        );
    }
}
```

**Step 2: Verify failure**

Run: `vendor/bin/phpunit tests/Enrichment/VitalityRankerTest.php`
Expected: `Class "AwesomeList\Enrichment\VitalityRanker" not found`.

**Step 3: Implement `lib/Enrichment/VitalityRanker.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

final class VitalityRanker
{
    private const MIN_CATEGORY_SIZE = 5;
    private const TOP_DECILE        = 0.10;

    /**
     * @param array<string, array{category: string, result: EnrichmentResult}> $results
     * @return array<string, array{category: string, result: EnrichmentResult}>
     */
    public function rank(array $results): array
    {
        $buckets = [];
        foreach ($results as $url => $row) {
            if (!isset($row['result']->typeData['github'])) {
                continue;
            }
            $buckets[$row['category']][$url] = $row['result']->typeData['github']['stars'] ?? 0;
        }

        $hotUrls = [];
        foreach ($buckets as $category => $stars) {
            $count = count($stars);
            if ($count < self::MIN_CATEGORY_SIZE) {
                continue;
            }
            arsort($stars);
            $cutoff = max(1, (int) floor($count * self::TOP_DECILE));
            $hotUrls = array_merge($hotUrls, array_slice(array_keys($stars), 0, $cutoff));
        }

        $hotSet = array_flip($hotUrls);
        foreach ($results as $url => $row) {
            $signals = $row['result']->signals;
            $signals['vitality_hot'] = isset($hotSet[$url]);
            $results[$url]['result'] = new EnrichmentResult(
                lastChecked: $row['result']->lastChecked,
                signals: $signals,
                typeData: $row['result']->typeData,
            );
        }
        return $results;
    }
}
```

**Step 4: Verify pass + full suite**

Run: `vendor/bin/phpunit`
Expected: 31 tests, OK.

**Step 5: Commit**

```bash
git add lib/Enrichment/VitalityRanker.php tests/Enrichment/VitalityRankerTest.php
git commit -m "feat: VitalityRanker marks top-10% per-category github entries hot"
```

---

## Task 6: `Enricher` orchestrator

Walks `data/**/*.yml`, loads entries, dispatches through `AdapterFactory`, hands the collected results to `VitalityRanker`, returns the final sidecar array.

**Files:**
- Create: `lib/Enrichment/Enricher.php`
- Create: `tests/Enrichment/EnricherTest.php`
- Create: `tests/fixtures/enrichment/data/frontends.yml`

**Step 1: Create a tiny data fixture `tests/fixtures/enrichment/data/frontends.yml`**

```yaml
- name: n98-magerun2
  url: https://github.com/netz98/n98-magerun2
  description: CLI.
  type: github_repo
  added: "2018-03-15"
- name: Hyvä
  url: https://hyva.io/
  description: Theme.
  type: vendor_site
  added: "2021-04-01"
```

**Step 2: Write the failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\Enricher;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\Enrichment\VitalityRanker;
use AwesomeList\YamlEntryLoader;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class EnricherTest extends TestCase
{
    public function test_it_enriches_only_supported_types_and_keys_by_url(): void
    {
        $repo    = (string) file_get_contents(__DIR__ . '/../fixtures/http/github/repos-active.json');
        $release = (string) file_get_contents(__DIR__ . '/../fixtures/http/github/releases-active.json');
        $mock    = new MockHandler([new Response(200, [], $repo), new Response(200, [], $release)]);
        $client  = new Client(['handler' => HandlerStack::create($mock), 'base_uri' => 'https://api.github.com/']);
        $adapter = new GithubRepoAdapter($client, new \DateTimeImmutable('2026-04-19T02:00:00Z'));

        $enricher = new Enricher(
            new YamlEntryLoader(),
            new AdapterFactory([$adapter]),
            new VitalityRanker(),
        );

        $state = $enricher->enrichDirectory(__DIR__ . '/../fixtures/enrichment/data');

        $this->assertArrayHasKey('https://github.com/netz98/n98-magerun2', $state);
        $this->assertArrayNotHasKey('https://hyva.io/', $state); // vendor_site has no Phase 2 adapter
        $this->assertSame(2147, $state['https://github.com/netz98/n98-magerun2']['github']['stars']);
        $this->assertTrue($state['https://github.com/netz98/n98-magerun2']['signals']['actively_maintained']);
    }
}
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/Enrichment/EnricherTest.php`
Expected: `Class "AwesomeList\Enrichment\Enricher" not found`.

**Step 4: Implement `lib/Enrichment/Enricher.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\YamlEntryLoader;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use RuntimeException;

final class Enricher
{
    public function __construct(
        private readonly YamlEntryLoader $loader,
        private readonly AdapterFactory $adapters,
        private readonly VitalityRanker $ranker,
    ) {}

    /** @return array<string, array<string, mixed>> sidecar state keyed by url */
    public function enrichDirectory(string $dataDir): array
    {
        if (!is_dir($dataDir)) {
            throw new RuntimeException("Data directory not found: $dataDir");
        }

        $rows = [];
        foreach ($this->yamlFiles($dataDir) as $file) {
            $category = pathinfo($file, PATHINFO_FILENAME);
            foreach ($this->loader->load($file) as $entry) {
                $adapter = $this->adapters->for($entry->type);
                if ($adapter === null || $entry->url === null) {
                    continue;
                }
                $rows[$entry->url] = [
                    'category' => $category,
                    'result'   => $adapter->enrich($entry),
                ];
            }
        }

        $ranked = $this->ranker->rank($rows);
        $state  = [];
        foreach ($ranked as $url => $row) {
            $state[$url] = $row['result']->toArray();
        }
        return $state;
    }

    /** @return iterable<string> */
    private function yamlFiles(string $dir): iterable
    {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
        foreach ($it as $f) {
            if ($f->isFile() && $f->getExtension() === 'yml') {
                yield $f->getPathname();
            }
        }
    }
}
```

**Step 5: Verify pass + full suite**

Run: `vendor/bin/phpunit`
Expected: 32 tests, OK.

**Step 6: Commit**

```bash
git add lib/Enrichment/Enricher.php tests/Enrichment/EnricherTest.php tests/fixtures/enrichment/
git commit -m "feat: Enricher orchestrates adapters across data/**/*.yml"
```

---

## Task 7: `bin/enrich.php` CLI

Thin wrapper: wires production adapters, runs the enricher over `data/`, writes `state/enrichment.json` with pretty-printed JSON, reports what changed. Reads a `GITHUB_TOKEN` from the environment for authenticated API calls (5000 req/h instead of 60).

**Files:**
- Create: `bin/enrich.php`
- Create: `state/enrichment.json` (empty `{}` seed, committed)
- Modify: `composer.json` (add `"enrich": "php bin/enrich.php"`)

**Step 1: Create the seed sidecar**

```bash
mkdir -p state
printf '{}\n' > state/enrichment.json
```

**Step 2: Write `bin/enrich.php`**

```php
#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use AwesomeList\Enrichment\AdapterFactory;
use AwesomeList\Enrichment\Enricher;
use AwesomeList\Enrichment\GithubRepoAdapter;
use AwesomeList\Enrichment\VitalityRanker;
use AwesomeList\YamlEntryLoader;
use GuzzleHttp\Client;

$token = getenv('GITHUB_TOKEN') ?: null;
$headers = ['User-Agent' => 'awesome-magento2-enricher'];
if ($token !== null) {
    $headers['Authorization'] = "Bearer $token";
}

$http = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 15,
    'headers'  => $headers,
]);

$enricher = new Enricher(
    new YamlEntryLoader(),
    new AdapterFactory([new GithubRepoAdapter($http, new DateTimeImmutable())]),
    new VitalityRanker(),
);

$state = $enricher->enrichDirectory(__DIR__ . '/../data');
$path  = __DIR__ . '/../state/enrichment.json';
file_put_contents($path, json_encode($state, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");

$count = count($state);
echo "Enriched $count entries → $path\n";
```

**Step 3: Add composer script**

In `composer.json` `"scripts"`:

```json
"enrich": "php bin/enrich.php"
```

**Step 4: Sanity check (skipped if no token available)**

Run: `GITHUB_TOKEN=ghp_xxx composer enrich`
Expected: `Enriched 2 entries → …/state/enrichment.json`, file contains entries for the two github_repo URLs in `data/frontends.yml`.

If no token handy, the unauthenticated limit is 60 req/h which is still enough to hit this once manually.

**Step 5: Commit**

```bash
git add bin/enrich.php state/enrichment.json composer.json
git commit -m "feat: bin/enrich.php CLI wires enrichment pipeline"
```

---

## Task 8: Graveyard routing in `YamlEntryList`

The parser currently renders every entry in input order. Extend it to split entries into *active* (everything else, in-place) and *graveyard* (signals.graveyard_candidate && !pinned, appended as a `<details>` block at the end of the section). Pinned entries ignore the graveyard flag.

**Files:**
- Modify: `lib/Parser/YamlEntryList.php`
- Modify: `tests/Parser/YamlEntryListTest.php`
- Create: `tests/fixtures/entries/with-graveyard.yml`
- Create: `tests/fixtures/state/graveyard.json`

**Step 1: Create the fixture with a graveyard entry**

`tests/fixtures/entries/with-graveyard.yml`:

```yaml
- name: Active
  url: https://github.com/org/active
  description: Still going.
  type: github_repo
  added: "2020-01-01"
- name: Dead
  url: https://github.com/org/dead
  description: Archived.
  type: github_repo
  added: "2018-01-01"
- name: Canonical-Pinned
  url: https://github.com/org/pinned
  description: Pinned resource.
  type: github_repo
  added: "2016-01-01"
  pinned: true
  pin_reason: Canonical
```

`tests/fixtures/state/graveyard.json`:

```json
{
  "https://github.com/org/dead":   { "last_checked": "2026-04-19T02:00:00Z", "signals": { "graveyard_candidate": true } },
  "https://github.com/org/pinned": { "last_checked": "2026-04-19T02:00:00Z", "signals": { "graveyard_candidate": true } }
}
```

**Step 2: Extend the test**

Append to `tests/Parser/YamlEntryListTest.php`:

```php
public function test_graveyard_entries_are_routed_to_a_details_block(): void
{
    $parser = new YamlEntryList(sidecarPath: __DIR__ . '/../fixtures/state/graveyard.json');
    $parser->setFilename(__DIR__ . '/../fixtures/entries/with-graveyard.yml');
    $md = $parser->parseToMarkdown();

    // Active entry appears in the main list.
    $this->assertStringContainsString('- [Active](https://github.com/org/active) - Still going.', $md);

    // Pinned entry appears in main list even though signals say graveyard.
    $this->assertStringContainsString('- [Canonical-Pinned](https://github.com/org/pinned)', $md);

    // Graveyard entry is inside the details block.
    $this->assertStringContainsString('<details>', $md);
    $this->assertStringContainsString('<summary>🪦 Graveyard', $md);
    $this->assertStringContainsString('- [Dead](https://github.com/org/dead) - Archived.', $md);

    // Main list ends before the details block (graveyard is appended).
    $this->assertLessThan(
        strpos($md, '<details>'),
        strpos($md, 'Active'),
    );
}
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/Parser/YamlEntryListTest.php`
Expected: assertion failure — no `<details>` string, no graveyard split.

**Step 4: Update `lib/Parser/YamlEntryList.php`**

Replace `parseToMarkdown()`:

```php
public function parseToMarkdown(): string
{
    $entries = $this->loader->load($this->filename);
    $state   = SidecarState::loadOrEmpty($this->sidecarPath);

    $active    = [];
    $graveyard = [];
    foreach ($entries as $entry) {
        $signals = $entry->url !== null ? ($state->signalsFor($entry->url) ?? []) : [];
        $isGraveyard = !$entry->pinned && !empty($signals['graveyard_candidate']);
        if ($isGraveyard) {
            $graveyard[] = $this->formatLine($entry, $state);
        } else {
            $active[] = $this->formatLine($entry, $state);
        }
    }

    $out = implode("\n", $active);
    if ($graveyard !== []) {
        $out .= "\n\n<details>\n<summary>🪦 Graveyard — projects no longer recommended</summary>\n\n"
             . implode("\n", $graveyard)
             . "\n\n</details>";
    }
    return $out;
}
```

**Step 5: Verify pass + full suite**

Run: `vendor/bin/phpunit`
Expected: 33 tests, OK.

**Step 6: Commit**

```bash
git add lib/Parser/YamlEntryList.php tests/Parser/YamlEntryListTest.php tests/fixtures/entries/with-graveyard.yml tests/fixtures/state/graveyard.json
git commit -m "feat: YamlEntryList routes graveyard entries into details block"
```

---

## Task 9: `enrich.yml` GitHub Action

Runs nightly, authenticates against GitHub, regenerates `state/enrichment.json`, commits the change. `regenerate.yml` already triggers on `state/**` changes, so the README will auto-update downstream.

**Files:**
- Create: `.github/workflows/enrich.yml`

**Step 1: Workflow file**

```yaml
name: Enrich entries

on:
  schedule:
    - cron: '0 2 * * 1'   # Mondays 02:00 UTC
  workflow_dispatch:

concurrency:
  group: enrich
  cancel-in-progress: false

jobs:
  enrich:
    if: github.actor != 'github-actions[bot]'
    runs-on: ubuntu-latest
    permissions:
      contents: write
    steps:
      - uses: actions/checkout@v4
        with:
          token: ${{ secrets.GITHUB_TOKEN }}

      - uses: shivammathur/setup-php@v2
        with:
          php-version: '8.2'
          coverage: none
          tools: composer:v2

      - run: composer install --no-interaction --no-progress

      - run: composer enrich
        env:
          GITHUB_TOKEN: ${{ secrets.GITHUB_TOKEN }}

      - name: Commit enrichment state
        run: |
          if git diff --quiet state/enrichment.json; then
            echo "No enrichment changes"
            exit 0
          fi
          git config user.name  'github-actions[bot]'
          git config user.email 'github-actions[bot]@users.noreply.github.com'
          git add state/enrichment.json
          git commit -m 'chore: refresh enrichment state'
          git push
```

Note: the commit message intentionally omits `[skip ci]` — we *want* `regenerate.yml` to fire on the `state/**` change and rebuild the README.

**Step 2: Local smoke test of the enrich step**

Run: `composer enrich` (with or without token).
Expected: `Enriched 2 entries → state/enrichment.json`, file has real data.

Run: `vendor/bin/phpunit && composer validate-data`
Expected: both green.

**Step 3: Commit (state file change optional — decide based on whether the local run looks sensible)**

```bash
git add .github/workflows/enrich.yml
# Only stage state/enrichment.json if you ran enrich locally and want the real data as initial state;
# otherwise leave it as the {} seed from Task 7.
git commit -m "ci: weekly enrichment workflow + sidecar refresh"
```

---

## Task 10: Update `CLAUDE.md` and `contributing.md`

Short doc update reflecting:
- `state/enrichment.json` exists and is bot-maintained — contributors don't edit it.
- `composer enrich` runs the pipeline locally (needs `GITHUB_TOKEN`).
- 🔥 and 🫡 badges are derived signals; pin an entry with `pinned: true` to opt out of graveyard routing.

**Files:**
- Modify: `CLAUDE.md`
- Modify: `contributing.md`

**Step 1: In `CLAUDE.md`, under "Content architecture", add a bullet after the JSON Schema line:**

```markdown
- Sidecar state at `state/enrichment.json` holds bot-derived signals keyed by entry URL. Written by the `enrich.yml` workflow (Mondays 02:00 UTC) — do not edit by hand. Read by `YamlEntryList` to render 🔥 (top-10% stars in category), 🫡 (actively maintained), and a graveyard `<details>` block for archived/stale entries.
```

**Step 2: In `contributing.md`, append to the YAML-flow section:**

```markdown
### Graveyard and badges

Entries flagged `graveyard_candidate` in `state/enrichment.json` move into a collapsed "Graveyard" block at the bottom of their section. Mark an entry `pinned: true` (with a `pin_reason`) to opt out — useful for canonical resources that won't see modern activity.

🔥 marks the top 10% of a category by stars (github_repo only; minimum 5 entries per category to enable). 🫡 marks actively maintained projects (commit in last 90 days + release in last year).
```

**Step 3: Commit**

```bash
git add CLAUDE.md contributing.md
git commit -m "docs: document enrichment pipeline + graveyard semantics"
```

---

## Task 11: End-to-end verification

**Step 1: Run the pipeline locally**

```bash
composer enrich                # writes state/enrichment.json
php generate.php               # regenerates README.md
git diff README.md state/enrichment.json
```

Expected: `state/enrichment.json` has real entries for the two github_repo URLs in `data/frontends.yml`; `README.md` shows 🫡 next to actively-maintained repos (Alokai likely, ScandiPWA likely). No graveyard block yet (neither is archived/stale). No 🔥 (category has < 5 github entries).

**Step 2: Run every verification gate**

```bash
composer validate-data && vendor/bin/phpunit
```

Expected: both green.

**Step 3: If the README diff surprises you, investigate**

Each difference is either a correct signal (commit and document it) or a bug (fix it). Do not paper over.

**Step 4: Decide whether to commit the enriched state**

If the real enrichment data looks sensible, commit `state/enrichment.json` and the regenerated `README.md` as a final "baseline" commit:

```bash
git add state/enrichment.json README.md
git commit -m "chore: initial enrichment baseline"
```

Otherwise leave `state/enrichment.json` as the `{}` seed and let CI produce the first real baseline on its next scheduled run.

---

## Task 12: Push

```bash
git push origin master
```

The `enrich.yml` workflow will pick up its next scheduled run Monday 02:00 UTC. You can also trigger it manually from the Actions tab (`workflow_dispatch`).

---

## Phase 3+ (separate plans)

- **Phase 3 — Discovery**: `discover.yml` workflow, weekly candidates issue template, `accept-candidate.yml` triggered by issue edits, `candidates.log.json` dedup.
- **Phase 4a — Remaining adapters**: blog (RSS autodiscovery), packagist_pkg, event (HTTP + year-regex), youtube_playlist (YouTube Data API), course/vendor_site/canonical (HTTP liveness), plus the 90-day 404 hard-delete rule.
- **Phase 4b — Content migration**: Tools, Extensions subcategories, Blogs, Other Awesome Lists, Platforms, Official Resources, Localization, Learning, Events + Meet Magento, Masters, Trustworthy Developers. One commit per category with a before/after diff.
- **Phase 5 — iCal/JSON feeds** (per #105): emit `events.ical` and `events.json` from `data/events/**` during regeneration.
