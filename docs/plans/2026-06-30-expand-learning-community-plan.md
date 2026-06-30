# Expand Learning & Community Sections Implementation Plan

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Add Podcasts, Newsletters, and Community sections to awesome-magento2 with a new `PodcastAdapter` enrichment type and curated initial entries drawn from mageres.

**Architecture:** New `podcast` EntryType + `PodcastAdapter` (mirrors `BlogAdapter` with 365-day activity window); three new YAML data files wired into `content/main.md` via existing `YamlEntryList` parser; `canonical` type for newsletters and community entries where no RSS is available.

**Tech Stack:** PHP 8.1+, existing `AwesomeList\Enrichment` namespace, Guzzle HTTP, PHPUnit, Symfony YAML, JSON Schema.

---

### Task 1: Add `podcast` to EntryType enum and JSON schema

**Files:**
- Modify: `lib/EntryType.php`
- Modify: `schemas/entry.schema.json`

**Step 1: Add the enum case**

In `lib/EntryType.php`, add one line after `case Canonical = 'canonical';`:

```php
case Podcast = 'podcast';
```

The full enum should look like:
```php
enum EntryType: string
{
    case GithubRepo      = 'github_repo';
    case Blog            = 'blog';
    case PackagistPkg    = 'packagist_pkg';
    case Event           = 'event';
    case YoutubePlaylist = 'youtube_playlist';
    case Course          = 'course';
    case VendorSite      = 'vendor_site';
    case Archive         = 'archive';
    case Canonical       = 'canonical';
    case Podcast         = 'podcast';
}
```

**Step 2: Add `podcast` to the schema enum**

In `schemas/entry.schema.json`, extend the `type` enum array from:
```json
"enum": ["github_repo", "blog", "packagist_pkg", "event", "youtube_playlist", "course", "vendor_site", "archive", "canonical"]
```
to:
```json
"enum": ["github_repo", "blog", "packagist_pkg", "event", "youtube_playlist", "course", "vendor_site", "archive", "canonical", "podcast"]
```

**Step 3: Verify schema validates**

```bash
composer validate-data
```
Expected: `✅ All entries valid.` (no new data files yet so same result as before)

**Step 4: Commit**

```bash
git add lib/EntryType.php schemas/entry.schema.json
git commit -m "feat: add podcast EntryType and schema value"
```

---

### Task 2: Create test fixtures for PodcastAdapter

**Files:**
- Create: `tests/fixtures/http/podcast/html-with-rss-link.html`
- Create: `tests/fixtures/http/podcast/feed-active.xml`
- Create: `tests/fixtures/http/podcast/feed-stale.xml`
- Create: `tests/fixtures/http/podcast/html-no-rss-link.html`

**Step 1: Create the fixture directory and files**

`tests/fixtures/http/podcast/html-with-rss-link.html`:
```html
<html><head>
<link rel="alternate" type="application/rss+xml" href="https://example.com/feed" />
</head><body></body></html>
```

`tests/fixtures/http/podcast/html-no-rss-link.html`:
```html
<html><head><title>Podcast</title></head><body></body></html>
```

`tests/fixtures/http/podcast/feed-active.xml` — pubDate within the last 365 days (use a date 10 days ago relative to any reasonable "now", the test will supply `now = 2026-04-20`):
```xml
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <title>Example Podcast</title>
  <item>
    <title>Episode 42</title>
    <pubDate>Fri, 10 Apr 2026 09:00:00 +0000</pubDate>
  </item>
  <item>
    <title>Episode 41</title>
    <pubDate>Sun, 01 Feb 2026 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
```

`tests/fixtures/http/podcast/feed-stale.xml` — pubDate older than 730 days:
```xml
<?xml version="1.0"?>
<rss version="2.0"><channel>
  <title>Example Podcast</title>
  <item>
    <title>Episode 1</title>
    <pubDate>Mon, 01 Jan 2024 09:00:00 +0000</pubDate>
  </item>
</channel></rss>
```

**Step 2: Commit fixtures**

```bash
git add tests/fixtures/http/podcast/
git commit -m "test: add podcast HTTP fixtures"
```

---

### Task 3: Write the failing PodcastAdapterTest

**Files:**
- Create: `tests/Enrichment/PodcastAdapterTest.php`

**Step 1: Write the test file**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Enrichment;

use AwesomeList\Entry;
use AwesomeList\Enrichment\PodcastAdapter;
use AwesomeList\EntryType;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\TestCase;

final class PodcastAdapterTest extends TestCase
{
    public function test_active_feed_produces_actively_maintained(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),
            new Response(200, [], $feed),
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertFalse($result->signals['graveyard_candidate']);
        $this->assertSame('https://example.com/feed', $result->typeData['podcast']['feed_url']);
        $this->assertStringStartsWith('2026-04-10', $result->typeData['podcast']['last_episode']);
    }

    public function test_stale_feed_is_graveyard(): void
    {
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-with-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-stale.xml');
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
        $html = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/html-no-rss-link.html');
        $feed = (string) file_get_contents(__DIR__ . '/../fixtures/http/podcast/feed-active.xml');
        $adapter = $this->build([
            new Response(200, [], $html),  // root fetch
            new Response(404),              // /feed
            new Response(200, [], $feed),   // /feed/ (HEAD-like)
            new Response(200, [], $feed),   // /feed/ (body fetch)
        ], '2026-04-20T00:00:00Z');

        $result = $adapter->enrich($this->entry('https://example.com/'), []);

        $this->assertTrue($result->signals['actively_maintained']);
        $this->assertSame('https://example.com/feed/', $result->typeData['podcast']['feed_url']);
    }

    public function test_unreachable_host_is_graveyard(): void
    {
        $adapter = $this->build([new Response(404)], '2026-04-20T00:00:00Z');
        $result  = $adapter->enrich($this->entry('https://dead.example/'), []);

        $this->assertTrue($result->signals['graveyard_candidate']);
        $this->assertNull($result->typeData['podcast']['last_episode']);
    }

    public function test_type_returns_podcast(): void
    {
        $this->assertSame('podcast', (new PodcastAdapter(new Client(), new \DateTimeImmutable()))->type());
    }

    private function build(array $responses, string $nowIso): PodcastAdapter
    {
        $mock   = new MockHandler($responses);
        $client = new Client(['handler' => HandlerStack::create($mock), 'http_errors' => false]);
        return new PodcastAdapter($client, new \DateTimeImmutable($nowIso));
    }

    private function entry(string $url): Entry
    {
        return new Entry(
            name: 'test-podcast',
            url: $url,
            description: null,
            type: EntryType::Podcast,
            added: '2020-01-01',
        );
    }
}
```

**Step 2: Run test to verify it fails**

```bash
composer test -- --filter PodcastAdapterTest
```
Expected: FAIL — `Class "AwesomeList\Enrichment\PodcastAdapter" not found`

**Step 3: Commit the failing test**

```bash
git add tests/Enrichment/PodcastAdapterTest.php
git commit -m "test: add failing PodcastAdapterTest"
```

---

### Task 4: Implement PodcastAdapter

**Files:**
- Create: `lib/Enrichment/PodcastAdapter.php`

**Step 1: Create the adapter**

`lib/Enrichment/PodcastAdapter.php` — identical logic to `BlogAdapter` except type name, constants, and `typeData` key:

```php
<?php declare(strict_types=1);
namespace AwesomeList\Enrichment;

use AwesomeList\Entry;
use DateTimeImmutable;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\TransferException;

final class PodcastAdapter implements EnrichmentAdapter
{
    private const ACTIVE_EPISODE_DAYS    = 365;
    private const GRAVEYARD_EPISODE_DAYS = 730;
    private const FALLBACK_FEED_PATHS    = ['/feed', '/feed/', '/rss.xml', '/atom.xml'];

    public function __construct(
        private readonly Client $http,
        private readonly DateTimeImmutable $now,
    ) {}

    public function type(): string
    {
        return 'podcast';
    }

    public function enrich(Entry $entry, array $priorState): EnrichmentResult
    {
        $url      = $entry->url ?? '';
        $feedUrl  = $this->discoverFeed($url);
        $lastEpisode = $feedUrl !== null ? $this->latestEpisodeFrom($feedUrl) : null;

        $active = $lastEpisode !== null
            && $this->daysSince($lastEpisode) <= self::ACTIVE_EPISODE_DAYS;

        $graveyard = $lastEpisode === null
            || $this->daysSince($lastEpisode) > self::GRAVEYARD_EPISODE_DAYS;

        return new EnrichmentResult(
            lastChecked: $this->now->format('Y-m-d\TH:i:s\Z'),
            signals: [
                'actively_maintained' => $active,
                'graveyard_candidate' => $graveyard,
                'vitality_hot'        => false,
            ],
            typeData: ['podcast' => [
                'feed_url'     => $feedUrl,
                'last_episode' => $lastEpisode,
            ]],
        );
    }

    private function discoverFeed(string $url): ?string
    {
        $html = $this->fetchBody($url);
        if ($html === null) {
            return null;
        }
        if (preg_match(
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

    private function latestEpisodeFrom(string $feedUrl): ?string
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

**Step 2: Run tests to verify they pass**

```bash
composer test -- --filter PodcastAdapterTest
```
Expected: 5 tests, 5 passed.

**Step 3: Run full test suite**

```bash
composer test
```
Expected: All tests pass.

**Step 4: Commit**

```bash
git add lib/Enrichment/PodcastAdapter.php
git commit -m "feat: add PodcastAdapter with RSS freshness enrichment"
```

---

### Task 5: Register PodcastAdapter in bin/enrich.php

**Files:**
- Modify: `bin/enrich.php`

**Step 1: Add the use statement**

After `use AwesomeList\Enrichment\BlogAdapter;` add:
```php
use AwesomeList\Enrichment\PodcastAdapter;
```

**Step 2: Register in the AdapterFactory array**

After `new BlogAdapter($genericHttp, $now),` add:
```php
new PodcastAdapter($genericHttp, $now),
```

**Step 3: Verify the script runs without errors**

```bash
php bin/enrich.php 2>&1 | head -5
```
Expected: starts enriching (or exits with missing GITHUB_TOKEN warning, not a fatal error).

**Step 4: Commit**

```bash
git add bin/enrich.php
git commit -m "feat: register PodcastAdapter in enricher"
```

---

### Task 6: Create data/podcasts.yml

**Files:**
- Create: `data/podcasts.yml`

**Step 1: Create the file with curated entries**

```yaml
- name: MageTalk
  url: https://magetalk.com/
  description: Weekly Magento podcast hosted by Phillip Jackson and Kalen Jordan covering Magento news, interviews, and community topics.
  type: podcast
  added: "2026-06-30"
- name: Talk Commerce
  url: https://talk-commerce.com/podcasts/
  description: Brent Peterson's interviews with digital commerce practitioners, merchants, and platform experts.
  type: podcast
  added: "2026-06-30"
- name: The JetRails Podcast
  url: https://jetrails.com/podcast/
  description: Ecommerce-focused podcast from JetRails covering hosting, performance, and platform strategy.
  type: podcast
  added: "2026-06-30"
```

**Step 2: Validate schema**

```bash
composer validate-data
```
Expected: `✅ All entries valid.`

**Step 3: Commit**

```bash
git add data/podcasts.yml
git commit -m "feat: add data/podcasts.yml with 3 curated entries"
```

---

### Task 7: Create data/newsletters.yml

**Files:**
- Create: `data/newsletters.yml`

**Step 1: Create the file**

```yaml
- name: Mage Dispatch
  url: https://www.magedispatch.com/
  description: Community-driven newsletter collecting links the Magento community should know about.
  type: blog
  added: "2026-06-30"
- name: The Devletter
  url: https://www.maxpronko.com/the-devletter/
  description: Free weekly email digest by Max Pronko covering Magento 2 development tips and news.
  type: blog
  added: "2026-06-30"
- name: M Bytes Newsletter
  url: https://m.academy/newsletter/
  description: Weekly developer newsletter from M.academy delivering three free Magento video lessons every Thursday.
  type: blog
  added: "2026-06-30"
- name: Mageres Monthly Digest
  url: https://mailchi.mp/6a498018d9ef/mageres
  description: Hand-curated monthly newsletter by Alessandro Ronchi collecting useful Magento resources.
  type: canonical
  added: "2026-06-30"
```

**Step 2: Validate schema**

```bash
composer validate-data
```
Expected: `✅ All entries valid.`

**Step 3: Commit**

```bash
git add data/newsletters.yml
git commit -m "feat: add data/newsletters.yml with 4 curated entries"
```

---

### Task 8: Create data/community.yml

**Files:**
- Create: `data/community.yml`

**Step 1: Create the file with communities and associations**

```yaml
- name: Magento Stack Exchange
  url: https://magento.stackexchange.com/
  description: Q&A site for users and developers of the Magento e-Commerce platform.
  type: canonical
  added: "2026-06-30"
- name: Reddit r/Magento
  url: https://www.reddit.com/r/Magento/
  description: Magento community on Reddit — questions, news, and discussion.
  type: canonical
  added: "2026-06-30"
- name: ExtDN
  url: https://extdn.org/
  description: A network of leading Magento extension developers committed to quality and best practices.
  type: canonical
  added: "2026-06-30"
- name: Mage-OS Association
  url: https://mage-os.org/
  description: The community alliance ensuring the accessibility, longevity, and success of Magento Open Source.
  type: canonical
  added: "2026-06-30"
- name: Magento Association
  url: https://www.magentoassociation.org/home
  description: The open and powerful Magento ecosystem association.
  type: canonical
  added: "2026-06-30"
- name: Firegento
  url: https://firegento.com/
  description: A group of Magento enthusiasts developing open-source modules and organizing hackathons.
  type: canonical
  added: "2026-06-30"
- name: Dutchento
  url: https://www.dutchento.org/
  description: The Magento community in the Netherlands.
  type: canonical
  added: "2026-06-30"
- name: OpenGento
  url: https://opengento.fr/
  description: The Magento community in France.
  type: canonical
  added: "2026-06-30"
```

**Step 2: Validate schema**

```bash
composer validate-data
```
Expected: `✅ All entries valid.`

**Step 3: Commit**

```bash
git add data/community.yml
git commit -m "feat: add data/community.yml with 8 curated entries"
```

---

### Task 9: Add sections to content/main.md

**Files:**
- Modify: `content/main.md`

**Step 1: Add the three new sections**

After the last line of the `## Blogs` block (the line `{% file=data/blogs/other.yml parser="AwesomeList\Parser\YamlEntryList" %}`), insert:

```markdown

## Podcasts

{% file=data/podcasts.yml parser="AwesomeList\Parser\YamlEntryList" %}

## Newsletters

{% file=data/newsletters.yml parser="AwesomeList\Parser\YamlEntryList" %}

## Community

{% file=data/community.yml parser="AwesomeList\Parser\YamlEntryList" %}
```

**Step 2: Regenerate README and verify**

```bash
php generate.php
```
Expected: `README.md written.` (no errors)

```bash
grep -n "## Podcasts\|## Newsletters\|## Community" README.md
```
Expected: three matching lines with the new section headings.

**Step 3: Commit**

```bash
git add content/main.md README.md
git commit -m "feat: add Podcasts, Newsletters, and Community sections to README"
```

---

### Task 10: Final validation and push

**Step 1: Run full test suite**

```bash
composer test
```
Expected: All tests pass.

**Step 2: Validate all data**

```bash
composer validate-data
```
Expected: `✅ All entries valid.`

**Step 3: Close issue #106**

```bash
gh issue close 106 --comment "Resolved by expanding awesome-magento2 with Podcasts, Newsletters, and Community sections. Initial entries curated from mageres. The lists now serve complementary audiences: awesome-magento2 focuses on quality signals and automation; mageres on breadth."
```

**Step 4: Push to master**

```bash
git push origin master
```
