# Auto-Update Phase 4a — Remaining Enrichment Adapters

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Enrich every entry type that currently has no adapter — blogs (RSS), packagist packages, events (year-regex), YouTube playlists/channels, and plain-HTTP types (vendor_site / course / canonical / archive) — so the 🫡 / 🔥 / 🪦 signals apply to the full list, not just github_repo.

**Architecture:** Extend the existing `EnrichmentAdapter` interface to accept prior sidecar state (needed for multi-run link-status tracking). Implement one adapter per type, each using Guzzle's mock handler in tests with recorded HTTP fixtures. A single `LivenessAdapter` class handles vendor_site / course / canonical via parameterised type. `ArchiveAdapter` is a deliberate no-op. The `VitalityRanker` grows a second ranking branch that uses packagist downloads as the score for `packagist_pkg` entries. The Phase 2 `Enricher` is extended to load per-URL prior state and pass it through.

**Tech Stack:** PHP 8.3+, Guzzle 7, Symfony YAML, PHPUnit 12, JSON Schema draft-07. No new libraries.

**References:**
- Design doc: `docs/plans/2026-04-19-auto-update-design.md` (adapter catalog §, retirement thresholds §)
- Phase 2 plan: `docs/plans/2026-04-19-auto-update-phase2-plan.md`

**Out of scope** (deferred):
- Automatic YAML deletion after 90-day 404 (design specifies this as a human action — the bot only flags `graveyard_candidate: true` once `link_status_since > 90d`).
- Discovery bot (Phase 3).
- iCal/JSON event feeds (Phase 5).
- YouTube "single video" URLs (e.g. `www.youtube.com/watch?v=...`) — Phase 4a handles playlists and channels only; single-video entries should already be `type: course` (they are in `data/learning.yml`).

---

## Task 1: Extend `EnrichmentAdapter` interface to receive prior state

The Phase 2 interface takes only an `Entry`. Several Phase 4a adapters need to see the PREVIOUS sidecar record for that URL to compute `link_status_since` and detect state transitions (ok → broken). Adding a `$priorState` array param is a minimal breaking change.

**Files:**
- Modify: `lib/Enrichment/EnrichmentAdapter.php`
- Modify: `lib/Enrichment/GithubRepoAdapter.php`
- Modify: `lib/Enrichment/Enricher.php`
- Modify: `tests/Enrichment/GithubRepoAdapterTest.php`
- Modify: `tests/Enrichment/EnricherTest.php`

### Step 1: Update the interface

Replace `lib/Enrichment/EnrichmentAdapter.php` `enrich` signature:

```php
public function enrich(Entry $entry, array $priorState): EnrichmentResult;
```

The docblock should read: *"`$priorState` is the previous per-URL sidecar block (empty array if never enriched), allowing adapters to detect transitions and carry values like `link_status_since`."*

### Step 2: Update `GithubRepoAdapter` signature (ignore the new param)

Change the `enrich` method signature to `enrich(Entry $entry, array $priorState): EnrichmentResult`. Body stays the same — the GitHub adapter derives everything fresh from the API.

### Step 3: Update every test call site

`tests/Enrichment/GithubRepoAdapterTest.php`: every `$adapter->enrich($this->entry(...))` becomes `$adapter->enrich($this->entry(...), [])`.

### Step 4: Update `Enricher::enrichDirectory`

Read the prior state JSON at the start of the run and thread it through:

```php
public function enrichDirectory(string $dataDir, string $priorStatePath): array
{
    if (!is_dir($dataDir)) {
        throw new RuntimeException("Data directory not found: $dataDir");
    }

    $priorState = is_file($priorStatePath)
        ? (json_decode((string) file_get_contents($priorStatePath), true, flags: JSON_THROW_ON_ERROR) ?: [])
        : [];

    $rows = [];
    foreach ($this->yamlFiles($dataDir) as $file) {
        $category = pathinfo($file, PATHINFO_FILENAME);
        foreach ($this->loader->load($file) as $entry) {
            $adapter = $this->adapters->for($entry->type);
            if ($adapter === null || $entry->url === null) {
                continue;
            }
            try {
                $result = $adapter->enrich($entry, $priorState[$entry->url] ?? []);
            } catch (Throwable $e) {
                fwrite(STDERR, "skip {$entry->url}: {$e->getMessage()}\n");
                continue;
            }
            $rows[$entry->url] = [
                'category' => $category,
                'result'   => $result,
            ];
        }
    }
    // ... ranker and state build stay as-is
}
```

### Step 5: Update `EnricherTest`

The existing test calls `$enricher->enrichDirectory(__DIR__ . '/../fixtures/enrichment/data')`. Change to pass a second argument pointing at a non-existent path (so prior state is empty):

```php
$state = $enricher->enrichDirectory(
    __DIR__ . '/../fixtures/enrichment/data',
    __DIR__ . '/../fixtures/state/empty.json',
);
```

Add an assertion in a NEW test that the prior state is read when present and passed through — mock an adapter that asserts on `$priorState`. Keep it small:

```php
public function test_it_passes_prior_state_to_adapter(): void
{
    $seen = null;
    $spy = new class ($seen) implements \AwesomeList\Enrichment\EnrichmentAdapter {
        public function __construct(private ?array &$seen) {}
        public function type(): string { return 'github_repo'; }
        public function enrich(\AwesomeList\Entry $entry, array $priorState): \AwesomeList\Enrichment\EnrichmentResult
        {
            $this->seen = $priorState;
            return new \AwesomeList\Enrichment\EnrichmentResult('2026-04-20T00:00:00Z', []);
        }
    };

    $enricher = new Enricher(
        new YamlEntryLoader(),
        new AdapterFactory([$spy]),
        new VitalityRanker(),
    );
    $enricher->enrichDirectory(
        __DIR__ . '/../fixtures/enrichment/data',
        __DIR__ . '/../fixtures/state/enrichment.sample.json',
    );

    $this->assertNotNull($seen);
    $this->assertTrue($seen['signals']['vitality_hot']);
}
```

### Step 6: Update `bin/enrich.php`

Pass the sidecar path as the second arg:

```php
$state = $enricher->enrichDirectory(__DIR__ . '/../data', __DIR__ . '/../state/enrichment.json');
```

### Step 7: Run full suite and validate

Run: `vendor/bin/phpunit && composer validate-data`
Expected: all tests green (existing 36 + the 1 new = 37).

### Step 8: Commit

```bash
git add lib/Enrichment tests/Enrichment bin/enrich.php
git commit -m "refactor(enricher): pass prior sidecar state into adapters"
```

---

## Task 2: `ArchiveAdapter` — explicit no-op

Entries with `type: archive` never retire, never get badges. Make that explicit via an adapter that returns only `last_checked` so the sidecar at least proves the entry was seen.

**Files:**
- Create: `lib/Enrichment/ArchiveAdapter.php`
- Create: `tests/Enrichment/ArchiveAdapterTest.php`
- Modify: `bin/enrich.php` (register the adapter)

### Step 1: Write the failing test

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\ArchiveAdapter;
use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;

final class ArchiveAdapterTest extends TestCase
{
    public function test_it_returns_last_checked_with_no_signals(): void
    {
        $adapter = new ArchiveAdapter(new \DateTimeImmutable('2026-04-20T02:00:00Z'));
        $entry   = new Entry(
            name: 'Vinai Kopp',
            url: null,
            description: 'Community member',
            type: EntryType::Archive,
            added: '2017-01-01',
        );

        $result = $adapter->enrich($entry, []);

        $this->assertSame('2026-04-20T02:00:00Z', $result->lastChecked);
        $this->assertSame([], $result->signals);
        $this->assertSame([], $result->typeData);
    }

    public function test_type_returns_archive(): void
    {
        $this->assertSame('archive', (new ArchiveAdapter(new \DateTimeImmutable()))->type());
    }
}
```

### Step 2: Verify red

Run: `vendor/bin/phpunit tests/Enrichment/ArchiveAdapterTest.php`
Expected: `Class "AwesomeList\Enrichment\ArchiveAdapter" not found`.

### Step 3: Implement `lib/Enrichment/ArchiveAdapter.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;

final class ArchiveAdapter implements EnrichmentAdapter
{
    public function __construct(private readonly DateTimeImmutable $now) {}

    public function type(): string
    {
        return 'archive';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [],
        );
    }
}
```

### Step 4: Register in `bin/enrich.php`

Add `new ArchiveAdapter($now)` to the `AdapterFactory` list. Hoist the `$now = new DateTimeImmutable()` variable so both adapters share it.

### Step 5: Verify and commit

Run: `vendor/bin/phpunit && composer validate-data`
Expected: full suite green.

```bash
git add lib/Enrichment/ArchiveAdapter.php tests/Enrichment/ArchiveAdapterTest.php bin/enrich.php
git commit -m "feat: ArchiveAdapter records last_checked, emits no signals"
```

---

## Task 3: `LivenessAdapter` — HTTP liveness for `vendor_site` / `course` / `canonical`

These types have no semantic activity signal — only whether the URL is still alive. A single adapter handles all three; register three instances with different types.

Signal contract:
- `link_status`: `'ok'` if HTTP 2xx/3xx, `'broken'` otherwise.
- `link_status_since`: ISO timestamp of the most recent status change. Reused from prior state if the status is unchanged; set to `$now` otherwise.
- `graveyard_candidate`: `true` iff `link_status == 'broken'` AND `link_status_since` is more than 90 days before `$now`.
- No `actively_maintained` signal for these types (they don't produce activity).
- No `vitality_hot`.

**Files:**
- Create: `lib/Enrichment/LivenessAdapter.php`
- Create: `tests/Enrichment/LivenessAdapterTest.php`
- Modify: `bin/enrich.php`

### Step 1: Write the failing tests

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\LivenessAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class LivenessAdapterTest extends TestCase
{
    public function test_200_means_ok_with_no_graveyard(): void
    {
        $adapter = $this->build(new Response(200), new \DateTimeImmutable('2026-04-20T00:00:00Z'));
        $result  = $adapter->enrich($this->entry(), []);

        $this->assertSame('ok', $result->signals['link_status']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('2026-04-20T00:00:00Z', $result->typeData['liveness']['link_status_since']);
    }

    public function test_404_sets_broken_and_carries_link_status_since_forward(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => [
                'link_status_since' => '2026-03-01T00:00:00Z',
            ],
            'signals' => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(404), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertSame('broken', $result->signals['link_status']);
        $this->assertSame('2026-03-01T00:00:00Z', $result->typeData['liveness']['link_status_since']);
        $this->assertFalse($result->signals['graveyard_candidate']); // 50 days broken < 90
    }

    public function test_broken_more_than_90_days_triggers_graveyard(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => [
                'link_status_since' => '2026-01-10T00:00:00Z', // 100 days earlier
            ],
            'signals' => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(404), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_transition_from_broken_to_ok_resets_link_status_since(): void
    {
        $now = new \DateTimeImmutable('2026-04-20T00:00:00Z');
        $prior = [
            'liveness' => ['link_status_since' => '2025-01-01T00:00:00Z'],
            'signals'  => ['link_status' => 'broken'],
        ];
        $adapter = $this->build(new Response(200), $now);
        $result  = $adapter->enrich($this->entry(), $prior);

        $this->assertSame('ok', $result->signals['link_status']);
        $this->assertSame('2026-04-20T00:00:00Z', $result->typeData['liveness']['link_status_since']);
        $this->assertFalse($result->signals['graveyard_candidate']);
    }

    public function test_type_is_configurable(): void
    {
        $this->assertSame('vendor_site', (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'vendor_site'))->type());
        $this->assertSame('course',      (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'course'))->type());
        $this->assertSame('canonical',   (new LivenessAdapter(new Client(), new \DateTimeImmutable(), 'canonical'))->type());
    }

    private function build(Response $response, \DateTimeImmutable $now): LivenessAdapter
    {
        $mock   = new MockHandler([$response]);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        return new LivenessAdapter($client, $now, 'vendor_site');
    }

    private function entry(): Entry
    {
        return new Entry(
            name: 'Example',
            url: 'https://example.com',
            description: null,
            type: EntryType::VendorSite,
            added: '2020-01-01',
        );
    }
}
```

### Step 2: Verify red

Run: `vendor/bin/phpunit tests/Enrichment/LivenessAdapterTest.php`
Expected: `Class "AwesomeList\Enrichment\LivenessAdapter" not found`.

### Step 3: Implement `lib/Enrichment/LivenessAdapter.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class LivenessAdapter implements EnrichmentAdapter
{
    private const GRAVEYARD_DAYS = 90;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
        private readonly string $type,
    ) {}

    public function type(): string
    {
        return $this->type;
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $status      = $this->checkUrl($entry->url ?? '');
        $priorStatus = $priorState['signals']['link_status'] ?? null;
        $priorSince  = $priorState['liveness']['link_status_since'] ?? null;
        $since = ($priorStatus === $status && $priorSince !== null)
            ? $priorSince
            : $this->now->format('Y-m-d\TH:i:s\Z');

        $graveyard = $status === 'broken'
            && $this->daysSince($since) > self::GRAVEYARD_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'link_status'         => $status,
                'graveyard_candidate' => $graveyard,
            ],
            typeData: ['liveness' => ['link_status_since' => $since]],
        );
    }

    private function checkUrl(string $url): string
    {
        try {
            $response = $this->http->get($url, [
                'timeout'         => 10,
                'allow_redirects' => true,
                'http_errors'     => false,
            ]);
            $code = $response->getStatusCode();
            return $code >= 200 && $code < 400 ? 'ok' : 'broken';
        } catch (TransferException) {
            return 'broken';
        }
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
```

### Step 4: Verify green

Run: `vendor/bin/phpunit tests/Enrichment/LivenessAdapterTest.php`
Expected: 5 tests pass.

### Step 5: Register in `bin/enrich.php`

Append three instances to the `AdapterFactory` array:

```php
new LivenessAdapter($http, $now, 'vendor_site'),
new LivenessAdapter($http, $now, 'course'),
new LivenessAdapter($http, $now, 'canonical'),
```

Note: liveness checks use the same `$http` Guzzle client as the GitHub adapter — GitHub's `base_uri` of `api.github.com` does not apply because `$http->get('https://example.com/...')` uses the absolute URL. Verify this behaviour in one quick test.

Actually — since liveness hits arbitrary hosts while GitHub adapter uses `api.github.com` as `base_uri`, we need a SEPARATE Guzzle client without the `base_uri` for liveness. In `bin/enrich.php`:

```php
$githubHttp   = new Client([
    'base_uri' => 'https://api.github.com/',
    'timeout'  => 15,
    'headers'  => $headers,
]);
$genericHttp = new Client([
    'timeout'         => 10,
    'allow_redirects' => true,
    'http_errors'     => false,
]);
```

And pass `$genericHttp` to the LivenessAdapter instances.

### Step 6: Full suite + commit

Run: `vendor/bin/phpunit && composer validate-data`
Expected: green.

```bash
git add lib/Enrichment/LivenessAdapter.php tests/Enrichment/LivenessAdapterTest.php bin/enrich.php
git commit -m "feat: LivenessAdapter handles vendor_site/course/canonical liveness"
```

---

## Task 4: `PackagistAdapter`

Packagist publishes a JSON metadata endpoint at `https://packagist.org/packages/{vendor}/{name}.json`. Includes monthly download counts, `abandoned` flag, and release history.

Signal contract:
- `actively_maintained`: most recent release ≤ 180 days old AND not abandoned.
- `graveyard_candidate`: `abandoned` is truthy OR the package 404s.
- `vitality_hot`: set later by `VitalityRanker` using monthly downloads (Task 8).

typeData `packagist`:
- `downloads_total`: integer
- `downloads_monthly`: integer
- `last_release`: ISO timestamp of newest release (or null)
- `abandoned`: bool

**Files:**
- Create: `lib/Enrichment/PackagistAdapter.php`
- Create: `tests/Enrichment/PackagistAdapterTest.php`
- Create: `tests/fixtures/http/packagist/pkg-active.json`
- Create: `tests/fixtures/http/packagist/pkg-abandoned.json`
- Modify: `bin/enrich.php`

### Step 1: Fixtures

`tests/fixtures/http/packagist/pkg-active.json`:

```json
{
  "package": {
    "name": "magepal/magento2-google-tag-manager",
    "abandoned": false,
    "downloads": { "total": 50000, "monthly": 1200 },
    "versions": {
      "1.3.0": { "version": "1.3.0", "time": "2025-11-10T12:00:00+00:00" },
      "1.2.0": { "version": "1.2.0", "time": "2025-06-10T12:00:00+00:00" }
    }
  }
}
```

`tests/fixtures/http/packagist/pkg-abandoned.json`:

```json
{
  "package": {
    "name": "someone/defunct",
    "abandoned": "someone/replacement",
    "downloads": { "total": 12, "monthly": 0 },
    "versions": {
      "1.0.0": { "version": "1.0.0", "time": "2021-01-01T00:00:00+00:00" }
    }
  }
}
```

### Step 2: Write failing tests

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\PackagistAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PackagistAdapterTest extends TestCase
{
    public function test_active_package_is_actively_maintained(): void
    {
        $body    = (string) file_get_contents(__DIR__ . '/../fixtures/http/packagist/pkg-active.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/magepal/magento2-google-tag-manager'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(50000, $result->typeData['packagist']['downloads_total']);
        $this->assertSame(1200, $result->typeData['packagist']['downloads_monthly']);
        $this->assertSame('2025-11-10T12:00:00+00:00', $result->typeData['packagist']['last_release']);
        $this->assertFalse($result->typeData['packagist']['abandoned']);
    }

    public function test_abandoned_package_is_graveyard(): void
    {
        $body    = (string) file_get_contents(__DIR__ . '/../fixtures/http/packagist/pkg-abandoned.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/someone/defunct'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->typeData['packagist']['abandoned']);
    }

    public function test_404_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://packagist.org/packages/ghost/package'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['packagist']['last_release']);
    }

    public function test_type_returns_packagist_pkg(): void
    {
        $this->assertSame('packagist_pkg', (new PackagistAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): PackagistAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock)]);
        return new PackagistAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test',
            url: $url,
            description: null,
            type: EntryType::PackagistPkg,
            added: '2020-01-01',
        );
    }
}
```

### Step 3: Verify red, then implement

`lib/Enrichment/PackagistAdapter.php`:

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\ClientException;
use RuntimeException;

final class PackagistAdapter implements EnrichmentAdapter
{
    private const ACTIVE_RELEASE_DAYS = 180;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'packagist_pkg';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        [$vendor, $name] = $this->parseUrl($entry->url ?? '');
        $data = $this->fetch("https://packagist.org/packages/$vendor/$name.json");

        if ($data === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['packagist' => [
                    'downloads_total'   => 0,
                    'downloads_monthly' => 0,
                    'last_release'      => null,
                    'abandoned'         => false,
                ]],
            );
        }

        $pkg         = $data['package'] ?? [];
        $abandoned   = !empty($pkg['abandoned']);
        $downloads   = $pkg['downloads'] ?? [];
        $lastRelease = $this->newestReleaseDate($pkg['versions'] ?? []);
        $activelyMaintained = !$abandoned
            && $lastRelease !== null
            && $this->daysSince($lastRelease) <= self::ACTIVE_RELEASE_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $activelyMaintained,
                'graveyard_candidate' => $abandoned,
                'vitality_hot'        => false,
            ],
            typeData: ['packagist' => [
                'downloads_total'   => (int) ($downloads['total'] ?? 0),
                'downloads_monthly' => (int) ($downloads['monthly'] ?? 0),
                'last_release'      => $lastRelease,
                'abandoned'         => (bool) $abandoned,
            ]],
        );
    }

    private function parseUrl(string $url): array
    {
        $path = parse_url($url, PHP_URL_PATH) ?? '';
        if (!preg_match('~^/packages/([^/]+)/([^/]+)/?$~', $path, $m)) {
            throw new RuntimeException("PackagistAdapter cannot parse url: $url");
        }
        return [$m[1], $m[2]];
    }

    private function fetch(string $url): ?array
    {
        try {
            $response = $this->http->get($url, ['timeout' => 15]);
            return json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        } catch (ClientException $e) {
            if ($e->getCode() === 404) {
                return null;
            }
            throw $e;
        }
    }

    private function newestReleaseDate(array $versions): ?string
    {
        $times = [];
        foreach ($versions as $v) {
            if (!empty($v['time'])) {
                $times[] = $v['time'];
            }
        }
        if ($times === []) {
            return null;
        }
        rsort($times);
        return $times[0];
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
```

### Step 4: Register + verify + commit

Add `new PackagistAdapter($genericHttp, $now)` to the factory in `bin/enrich.php`.

Run: `vendor/bin/phpunit && composer validate-data`
Expected: green.

```bash
git add lib/Enrichment/PackagistAdapter.php tests/Enrichment/PackagistAdapterTest.php tests/fixtures/http/packagist/ bin/enrich.php
git commit -m "feat: PackagistAdapter enriches packagist_pkg entries"
```

---

## Task 5: `BlogAdapter` — RSS autodiscovery

Flow:
1. `GET` the blog URL.
2. Parse returned HTML for `<link rel="alternate" type="application/rss+xml" href="...">` OR `<link rel="alternate" type="application/atom+xml" href="...">`.
3. If no autodiscovery link, try common feed paths on the blog host: `/feed`, `/feed/`, `/rss.xml`, `/atom.xml`.
4. Fetch the feed (RSS or Atom) and parse the most-recent `<pubDate>` or `<updated>`.

Signal contract:
- `actively_maintained`: most-recent post ≤ 60 days old.
- `graveyard_candidate`: no post in 540 days (18 months) OR root URL broken > 90 days (reuse liveness logic — same `link_status_since` key).
- No `vitality_hot` signal for blogs.

typeData `blog`:
- `feed_url`: the resolved feed URL (null if none found).
- `last_post`: ISO timestamp of latest post (null if no feed / empty feed).

**Files:**
- Create: `lib/Enrichment/BlogAdapter.php`
- Create: `tests/Enrichment/BlogAdapterTest.php`
- Create: `tests/fixtures/http/blog/html-with-rss-link.html`
- Create: `tests/fixtures/http/blog/html-no-rss-link.html`
- Create: `tests/fixtures/http/blog/feed-active.xml`
- Create: `tests/fixtures/http/blog/feed-stale.xml`
- Modify: `bin/enrich.php`

### Step 1: Fixtures

`tests/fixtures/http/blog/html-with-rss-link.html`:
```html
<html><head>
<link rel="alternate" type="application/rss+xml" href="https://example.com/feed" />
</head><body></body></html>
```

`tests/fixtures/http/blog/html-no-rss-link.html`:
```html
<html><head></head><body>no feed link</body></html>
```

`tests/fixtures/http/blog/feed-active.xml` (RSS 2.0, latest item within 60 days of 2026-04-20):
```xml
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <title>Example</title>
  <item>
    <title>Fresh post</title>
    <pubDate>Thu, 10 Apr 2026 09:00:00 +0000</pubDate>
  </item>
  <item>
    <title>Older post</title>
    <pubDate>Mon, 01 Feb 2026 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
```

`tests/fixtures/http/blog/feed-stale.xml` (latest post >18mo before 2026-04-20):
```xml
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <title>Example</title>
  <item>
    <title>Ancient post</title>
    <pubDate>Mon, 01 Jan 2024 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
```

### Step 2: Write failing tests

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\BlogAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class BlogAdapterTest extends TestCase
{
    public function test_active_feed_produces_actively_maintained(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('https://example.com/feed', $result->typeData['blog']['feed_url']);
        $this->assertStringStartsWith('2026-04-10', $result->typeData['blog']['last_post']);
    }

    public function test_stale_feed_is_graveyard(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-stale.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_missing_feed_link_falls_back_to_common_paths(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/html-no-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/blog/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(404),        // /feed
            new Response(200, [], $feed), // /feed/
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('https://example.com/feed/', $result->typeData['blog']['feed_url']);
    }

    public function test_unreachable_host_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['blog']['last_post']);
    }

    public function test_type_returns_blog(): void
    {
        $this->assertSame('blog', (new BlogAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): BlogAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new BlogAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-blog',
            url: $url,
            description: null,
            type: EntryType::Blog,
            added: '2020-01-01',
        );
    }
}
```

### Step 3: Verify red, then implement

`lib/Enrichment/BlogAdapter.php`:

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class BlogAdapter implements EnrichmentAdapter
{
    private const ACTIVE_POST_DAYS     = 60;
    private const GRAVEYARD_POST_DAYS  = 540;
    private const FALLBACK_FEED_PATHS  = ['/feed', '/feed/', '/rss.xml', '/atom.xml'];

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'blog';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $url     = $entry->url ?? '';
        $feedUrl = $this->discoverFeed($url);
        $lastPost = $feedUrl !== null ? $this->latestPostFrom($feedUrl) : null;

        $active = $lastPost !== null
            && $this->daysSince($lastPost) <= self::ACTIVE_POST_DAYS;

        $graveyard = $lastPost === null
            || $this->daysSince($lastPost) > self::GRAVEYARD_POST_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: ['blog' => [
                'feed_url'  => $feedUrl,
                'last_post' => $lastPost,
            ]],
        );
    }

    private function discoverFeed(string $url): ?string
    {
        $html = $this->fetchBody($url);
        if ($html !== null && preg_match(
            '~<link[^>]+rel=["\']alternate["\'][^>]+type=["\']application/(rss|atom)\+xml["\'][^>]+href=["\']([^"\']+)["\']~i',
            $html,
            $m,
        )) {
            return $this->resolveUrl($url, $m[2]);
        }
        foreach (self::FALLBACK_FEED_PATHS as $path) {
            $candidate = $this->resolveUrl($url, $path);
            if ($this->headOk($candidate)) {
                return $candidate;
            }
        }
        return null;
    }

    private function latestPostFrom(string $feedUrl): ?string
    {
        $body = $this->fetchBody($feedUrl);
        if ($body === null) {
            return null;
        }
        $doc = @simplexml_load_string($body);
        if ($doc === false) {
            return null;
        }
        $dates = [];
        foreach ($doc->channel->item ?? [] as $item) {
            if (!empty((string) $item->pubDate)) {
                $dates[] = (string) $item->pubDate;
            }
        }
        foreach ($doc->entry ?? [] as $entry) {
            if (!empty((string) $entry->updated)) {
                $dates[] = (string) $entry->updated;
            }
        }
        if ($dates === []) {
            return null;
        }
        $timestamps = array_filter(array_map('strtotime', $dates));
        if ($timestamps === []) {
            return null;
        }
        return gmdate('Y-m-d\TH:i:s\Z', max($timestamps));
    }

    private function fetchBody(string $url): ?string
    {
        try {
            $response = $this->http->get($url, ['timeout' => 15]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            return (string) $response->getBody();
        } catch (TransferException) {
            return null;
        }
    }

    private function headOk(string $url): bool
    {
        try {
            $response = $this->http->get($url, ['timeout' => 10]);
            return $response->getStatusCode() < 400;
        } catch (TransferException) {
            return false;
        }
    }

    private function resolveUrl(string $base, string $relative): string
    {
        if (preg_match('~^https?://~i', $relative)) {
            return $relative;
        }
        $baseHost = rtrim(
            parse_url($base, PHP_URL_SCHEME) . '://' . parse_url($base, PHP_URL_HOST),
            '/',
        );
        return $baseHost . '/' . ltrim($relative, '/');
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
```

### Step 4: Register + verify + commit

Add `new BlogAdapter($genericHttp, $now)` to factory.

Run: `vendor/bin/phpunit && composer validate-data`

```bash
git add lib/Enrichment/BlogAdapter.php tests/Enrichment/BlogAdapterTest.php tests/fixtures/http/blog/ bin/enrich.php
git commit -m "feat: BlogAdapter discovers RSS/Atom feed and tracks last_post"
```

---

## Task 6: `EventAdapter` — HTTP + year regex

For event pages, the simplest freshness proxy the design doc calls for is "the most-recent 4-digit year mentioned on the page". An event whose site still says `2026` in April 2026 is alive; a site that only mentions `2018` is dormant.

Signal contract:
- `actively_maintained`: page contains a year ≥ current year (or current-1 during the first 3 months of the calendar year, to avoid early-January false negatives).
- `graveyard_candidate`: page 404s, OR no mention of current-year-or-newer for 60 days (tracked via `link_status_since`).
- No `vitality_hot`.

typeData `event`:
- `latest_year_on_page`: integer, or null if not found.
- `link_status_since`: carried forward if unchanged.

**Files:**
- Create: `lib/Enrichment/EventAdapter.php`
- Create: `tests/Enrichment/EventAdapterTest.php`
- Create: `tests/fixtures/http/event/page-current.html`
- Create: `tests/fixtures/http/event/page-old.html`
- Modify: `bin/enrich.php`

### Step 1: Fixtures

`tests/fixtures/http/event/page-current.html`:
```html
<html><body>
<h1>MageUnconference 2026</h1>
<p>Join us 15 October 2026 in Cologne.</p>
</body></html>
```

`tests/fixtures/http/event/page-old.html`:
```html
<html><body>
<h1>Event 2019</h1>
<p>The 2019 edition was a success.</p>
</body></html>
```

### Step 2: Failing tests

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\EventAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class EventAdapterTest extends TestCase
{
    public function test_current_year_page_is_actively_maintained(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/event/page-current.html');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame(2026, $result->typeData['event']['latest_year_on_page']);
    }

    public function test_page_with_only_old_years_is_not_active(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/event/page-old.html');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertFalse($result->signals['actively_maintained']);
        $this->assertSame(2019, $result->typeData['event']['latest_year_on_page']);
    }

    public function test_404_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_type_returns_event(): void
    {
        $this->assertSame('event', (new EventAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): EventAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new EventAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-event',
            url: $url,
            description: null,
            type: EntryType::Event,
            added: '2020-01-01',
        );
    }
}
```

### Step 3: Implement `lib/Enrichment/EventAdapter.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class EventAdapter implements EnrichmentAdapter
{
    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'event';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $body = $this->fetchBody($entry->url ?? '');
        if ($body === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['event' => ['latest_year_on_page' => null]],
            );
        }

        $latestYear = $this->scanYears($body);
        $currentYear = (int) $this->now->format('Y');
        $active = $latestYear !== null && $latestYear >= $currentYear;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => false,
                'vitality_hot'        => false,
            ],
            typeData: ['event' => ['latest_year_on_page' => $latestYear]],
        );
    }

    private function scanYears(string $body): ?int
    {
        if (!preg_match_all('/(?<!\d)(20\d{2})(?!\d)/', $body, $m)) {
            return null;
        }
        $currentYear = (int) $this->now->format('Y');
        $years = array_map('intval', $m[1]);
        $years = array_filter($years, fn(int $y): bool => $y <= $currentYear + 1);
        if ($years === []) {
            return null;
        }
        return max($years);
    }

    private function fetchBody(string $url): ?string
    {
        try {
            $response = $this->http->get($url, ['timeout' => 15]);
            if ($response->getStatusCode() >= 400) {
                return null;
            }
            return (string) $response->getBody();
        } catch (TransferException) {
            return null;
        }
    }
}
```

### Step 4: Register + verify + commit

Add `new EventAdapter($genericHttp, $now)` to factory.

```bash
git add lib/Enrichment/EventAdapter.php tests/Enrichment/EventAdapterTest.php tests/fixtures/http/event/ bin/enrich.php
git commit -m "feat: EventAdapter tracks most-recent year mentioned on event page"
```

---

## Task 7: `YoutubePlaylistAdapter` — YouTube Data API v3

Requires a `YOUTUBE_API_KEY` env var. If missing, the adapter logs-and-skips (returns a minimal result with `last_checked` and nothing else) so local runs without the key don't explode.

URL shapes we accept:
- `https://www.youtube.com/playlist?list=PL...` — playlist
- `https://www.youtube.com/channel/UC...` — channel

Signal contract:
- `actively_maintained`: most recent upload ≤ 90 days.
- `graveyard_candidate`: no upload in 540 days OR 404.
- `vitality_hot`: false (subscriber count > 10k needed, Phase 4a skips that ranking — leave false).

typeData `youtube`:
- `last_upload`: ISO timestamp or null
- `channel_id` or `playlist_id`
- `title`: channel/playlist title

**Files:**
- Create: `lib/Enrichment/YoutubePlaylistAdapter.php`
- Create: `tests/Enrichment/YoutubePlaylistAdapterTest.php`
- Create: `tests/fixtures/http/youtube/playlist-items.json`
- Create: `tests/fixtures/http/youtube/channel-videos.json`
- Modify: `bin/enrich.php`

### Step 1: Fixtures

`tests/fixtures/http/youtube/playlist-items.json` (abbreviated — real YouTube response shape):

```json
{
  "items": [
    {
      "snippet": {
        "publishedAt": "2026-03-01T12:00:00Z",
        "title": "Latest upload"
      }
    },
    {
      "snippet": {
        "publishedAt": "2025-11-01T12:00:00Z",
        "title": "Older upload"
      }
    }
  ]
}
```

`tests/fixtures/http/youtube/channel-videos.json`:

```json
{
  "items": [
    {
      "snippet": {
        "publishedAt": "2024-01-01T12:00:00Z",
        "title": "Ancient"
      }
    }
  ]
}
```

### Step 2: Failing tests

(Abbreviated — follow the pattern of previous adapter tests. Cover: active playlist, stale channel, missing API key skips cleanly, 404 → graveyard.)

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\YoutubePlaylistAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class YoutubePlaylistAdapterTest extends TestCase
{
    public function test_recent_playlist_is_actively_maintained(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/youtube/playlist-items.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z', 'fake-key');
        $result = $adapter->enrich($this->playlistEntry(), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('2026-03-01T12:00:00Z', $result->typeData['youtube']['last_upload']);
    }

    public function test_stale_channel_is_graveyard(): void
    {
        $body = (string) file_get_contents(__DIR__ . '/../fixtures/http/youtube/channel-videos.json');
        $adapter = $this->build([new Response(200, [], $body)], '2026-04-20T00:00:00Z', 'fake-key');
        $result = $adapter->enrich($this->channelEntry(), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
    }

    public function test_missing_api_key_returns_empty_result(): void
    {
        $adapter = $this->build([], '2026-04-20T00:00:00Z', null);
        $result  = $adapter->enrich($this->playlistEntry(), []);

        $this->assertSame('2026-04-20T00:00:00Z', $result->lastChecked);
        $this->assertSame([], $result->signals);
    }

    public function test_type_returns_youtube_playlist(): void
    {
        $this->assertSame('youtube_playlist', (new YoutubePlaylistAdapter(new Client(), new \DateTimeImmutable(), null))->type());
    }

    private function build(array $responses, string $nowIso, ?string $apiKey): YoutubePlaylistAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new YoutubePlaylistAdapter($client, new \DateTimeImmutable($nowIso), $apiKey);
    }

    private function playlistEntry(): Entry
    {
        return new Entry(
            name: 'Test Playlist',
            url: 'https://www.youtube.com/playlist?list=PLxyz',
            description: null,
            type: EntryType::YoutubePlaylist,
            added: '2020-01-01',
        );
    }

    private function channelEntry(): Entry
    {
        return new Entry(
            name: 'Test Channel',
            url: 'https://www.youtube.com/channel/UCxyz',
            description: null,
            type: EntryType::YoutubePlaylist,
            added: '2020-01-01',
        );
    }
}
```

### Step 3: Implement `lib/Enrichment/YoutubePlaylistAdapter.php`

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class YoutubePlaylistAdapter implements EnrichmentAdapter
{
    private const ACTIVE_UPLOAD_DAYS    = 90;
    private const GRAVEYARD_UPLOAD_DAYS = 540;

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
        private readonly ?string $apiKey,
    ) {}

    public function type(): string
    {
        return 'youtube_playlist';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        if ($this->apiKey === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [],
            );
        }

        $url = $entry->url ?? '';
        $endpoint = $this->endpointFor($url);
        if ($endpoint === null) {
            return new EnrichmentResult(
                lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
                signals: [
                    'actively_maintained' => false,
                    'graveyard_candidate' => true,
                    'vitality_hot'        => false,
                ],
                typeData: ['youtube' => ['last_upload' => null]],
            );
        }

        $items = $this->fetchItems($endpoint);
        $lastUpload = $this->newestItemDate($items);

        $active = $lastUpload !== null
            && $this->daysSince($lastUpload) <= self::ACTIVE_UPLOAD_DAYS;
        $graveyard = $lastUpload === null
            || $this->daysSince($lastUpload) > self::GRAVEYARD_UPLOAD_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: ['youtube' => ['last_upload' => $lastUpload]],
        );
    }

    /** Returns a full `https://www.googleapis.com/youtube/v3/...` URL or null for unparseable input. */
    private function endpointFor(string $url): ?string
    {
        if (preg_match('~[?&]list=([A-Za-z0-9_-]+)~', $url, $m)) {
            return sprintf(
                'https://www.googleapis.com/youtube/v3/playlistItems?part=snippet&maxResults=5&playlistId=%s&key=%s',
                $m[1], $this->apiKey,
            );
        }
        if (preg_match('~/channel/(UC[A-Za-z0-9_-]+)~', $url, $m)) {
            return sprintf(
                'https://www.googleapis.com/youtube/v3/search?part=snippet&order=date&type=video&maxResults=5&channelId=%s&key=%s',
                $m[1], $this->apiKey,
            );
        }
        return null;
    }

    private function fetchItems(string $endpoint): array
    {
        try {
            $response = $this->http->get($endpoint, ['timeout' => 15]);
            if ($response->getStatusCode() >= 400) {
                return [];
            }
            $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
            return $body['items'] ?? [];
        } catch (TransferException) {
            return [];
        }
    }

    private function newestItemDate(array $items): ?string
    {
        $dates = [];
        foreach ($items as $item) {
            $published = $item['snippet']['publishedAt'] ?? null;
            if ($published !== null) {
                $dates[] = $published;
            }
        }
        if ($dates === []) {
            return null;
        }
        rsort($dates);
        return $dates[0];
    }

    private function daysSince(string $iso): int
    {
        $then = new DateTimeImmutable($iso);
        $diff = $this->now->diff($then);
        return $diff->invert === 1 ? (int) $diff->days : 0;
    }
}
```

### Step 4: Register + verify + commit

In `bin/enrich.php`:

```php
$youtubeKey = getenv('YOUTUBE_API_KEY') ?: null;
// ...
new YoutubePlaylistAdapter($genericHttp, $now, $youtubeKey),
```

```bash
git add lib/Enrichment/YoutubePlaylistAdapter.php tests/Enrichment/YoutubePlaylistAdapterTest.php tests/fixtures/http/youtube/ bin/enrich.php
git commit -m "feat: YoutubePlaylistAdapter enriches playlists and channels"
```

Also add a `YOUTUBE_API_KEY: ${{ secrets.YOUTUBE_API_KEY }}` env entry to `.github/workflows/enrich.yml`. If the secret isn't set, the workflow still runs — the adapter silently skips those entries.

---

## Task 8: Extend `VitalityRanker` for packagist downloads

Current ranker scores only `typeData.github.stars`. Add a second branch that scores by `typeData.packagist.downloads_monthly` when present. Rule: an entry participates in ONE ranking — github if it has github data, otherwise packagist.

**Files:**
- Modify: `lib/Enrichment/VitalityRanker.php`
- Modify: `tests/Enrichment/VitalityRankerTest.php`

### Step 1: Add a failing test

```php
public function test_it_marks_top_decile_for_packagist_by_monthly_downloads(): void
{
    $results = [];
    for ($i = 1; $i <= 10; $i++) {
        $results["url-$i"] = [
            'category' => 'extensions-marketing',
            'result'   => new EnrichmentResult(
                '2026-04-20T00:00:00Z',
                ['vitality_hot' => false],
                ['packagist' => ['downloads_monthly' => $i * 100]],
            ),
        ];
    }
    $ranked = (new VitalityRanker())->rank($results);
    $this->assertTrue($ranked['url-10']['result']->signals['vitality_hot']);
    $this->assertFalse($ranked['url-1']['result']->signals['vitality_hot']);
}
```

### Step 2: Update `rank()` to handle both branches

Replace the score-extraction block so each entry contributes to the bucket matching its typeData key:

```php
$buckets = [];
foreach ($results as $url => $row) {
    $td = $row['result']->typeData;
    if (isset($td['github'])) {
        $buckets[$row['category'] . ':github'][$url] = $td['github']['stars'] ?? 0;
    } elseif (isset($td['packagist'])) {
        $buckets[$row['category'] . ':packagist'][$url] = $td['packagist']['downloads_monthly'] ?? 0;
    }
}
```

The rest of the method (cutoff + hot-set + rewrite) stays the same.

### Step 3: Verify + commit

Run: `vendor/bin/phpunit`

```bash
git add lib/Enrichment/VitalityRanker.php tests/Enrichment/VitalityRankerTest.php
git commit -m "feat(ranker): extend vitality_hot to packagist downloads"
```

---

## Task 9: Update docs

**Files:**
- Modify: `CLAUDE.md`
- Modify: `contributing.md`

### Step 1: Content changes

In `CLAUDE.md`, expand the "Content architecture" bullet that mentions enrichment to read:

> - Sidecar state at `state/enrichment.json` holds bot-derived signals keyed by entry URL. Written by the `enrich.yml` workflow (Mondays 02:00 UTC) — do not edit by hand. Read by `YamlEntryList` to render 🔥 (top-10% GitHub stars *or* packagist monthly downloads in category), 🫡 (actively maintained per type-specific rules), and a graveyard `<details>` block for archived/stale/broken entries. Enrichment adapters live in `lib/Enrichment/` — one per `type`: `github_repo`, `packagist_pkg`, `blog`, `event`, `youtube_playlist`, `vendor_site`, `course`, `canonical`, `archive`.

In `contributing.md`, under the "Adding an entry (YAML flow)" section, add a short paragraph:

> Every entry type is automatically checked for freshness and link-liveness by the weekly enrichment job. Entries that go stale (archived repo, abandoned composer package, no blog post in 18 months, etc.) move to a collapsed graveyard block. No manual intervention is required.

Mention the `YOUTUBE_API_KEY` repo secret: "Set a `YOUTUBE_API_KEY` repo secret if you want YouTube entries enriched. Without it, the YouTube adapter skips those entries silently."

### Step 2: Commit

```bash
git add CLAUDE.md contributing.md
git commit -m "docs: document Phase 4a adapter types + YOUTUBE_API_KEY"
```

---

## Task 10: End-to-end verification

### Step 1: Run the full pipeline locally

```bash
composer enrich    # with or without GITHUB_TOKEN / YOUTUBE_API_KEY
php generate.php
git diff README.md state/enrichment.json
```

Expected:
- Blog entries with live RSS feeds pick up 🫡 if their most recent post is ≤ 60 days old.
- Packagist packages (if any made it into `data/**` during migrations) get 🫡 based on release date.
- Event pages with current-year content get 🫡.
- Influencer entries (`type: archive`) get `last_checked` in the sidecar but no badges.
- Dead URLs across types produce `link_status: broken` and, after 90+ days, 🪦.

### Step 2: Suite + schema

```bash
vendor/bin/phpunit && composer validate-data
```

Expected: all green.

### Step 3: Hand-inspect the sidecar

`state/enrichment.json` should now contain per-URL blocks for (almost) every entry — not just the github ones. The exception is YouTube entries if no API key is set locally.

### Step 4: If anything looks off

Stop and investigate. Common causes:
- A blog's RSS feed lives at a non-standard path → add to `FALLBACK_FEED_PATHS` or file an issue.
- A packagist entry still uses an old `github.com/...` URL → fix the `type` or the `url` in the YAML.
- A YouTube URL points at `/watch?v=` (single video) → should be `type: course` instead.

---

## Task 11: Merge + trigger workflow

### Step 1: Merge feature branch

```bash
git checkout master
git merge --ff-only <feature-branch>
git push origin master
```

### Step 2: Trigger enrich once so the full new sidecar baseline lands

```bash
gh workflow run enrich.yml --ref master
gh run list --workflow=enrich.yml --limit 1
```

### Step 3: Verify the resulting README on master

```bash
git pull
grep -cE '🔥|🫡|🪦' README.md
```

Expected: badge count grows substantially from today's ~46 (6 🔥 + 31 🫡 + 9 🪦) to somewhere between 80-120 as blog/event/packagist entries start producing signals.

---

## Phase 5 preview (separate plan)

- Emit `events.ical` and `events.json` from `data/events/**` during regeneration (per #105).
- Potentially: GitHub Discovery bot (Phase 3) dispatched from a separate plan.
