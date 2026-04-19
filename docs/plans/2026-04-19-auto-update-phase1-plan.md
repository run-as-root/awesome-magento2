# Auto-Update Phase 1 — Foundations & Frontends Migration

> **For Claude:** REQUIRED SUB-SKILL: Use superpowers:executing-plans to implement this plan task-by-task.

**Goal:** Establish the YAML-based data model, sidecar state file, new `YamlEntryList` parser, and a GitHub Action that regenerates `README.md` on data changes. Prove it end-to-end by migrating the Frontends section.

**Architecture:** Extend the existing `lib/Parser/` tag-templating engine with a new parser that reads YAML entry files plus an (initially empty) sidecar state file. No enrichment, no discovery, no adapters in Phase 1 — those land in Phase 2. Strangler pattern: all non-Frontends README content moves verbatim into `content/main.md` so the generator already owns the whole document; future phases replace sections one by one.

**Tech Stack:** PHP 8.1+, Composer, `symfony/yaml`, PHPUnit 10, GitHub Actions, JSON Schema draft-07.

**References:**
- Design doc: `docs/plans/2026-04-19-auto-update-design.md`
- Prior art: [#102](https://github.com/run-as-root/awesome-magento2/issues/102)

**Out of scope for Phase 1** (follow-up plans): enrichment adapters, 🔥/🫡 badges, graveyard rendering, discovery bot, candidates issue, accept-candidate workflow, migrating sections other than Frontends.

---

## Task 0: Add PHPUnit and `symfony/yaml`

The repo has no test suite today. We need one before any TDD work.

**Files:**
- Modify: `composer.json`
- Create: `phpunit.xml`
- Create: `tests/bootstrap.php`

**Step 1: Add dev dependencies**

Run: `composer require --dev phpunit/phpunit:^10 && composer require symfony/yaml:^6`

Expected: `composer.json` updated, `composer.lock` regenerated, `vendor/` populated.

**Step 2: Create `phpunit.xml`**

```xml
<?xml version="1.0" encoding="UTF-8"?>
<phpunit xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance"
         xsi:noNamespaceSchemaLocation="vendor/phpunit/phpunit/phpunit.xsd"
         bootstrap="tests/bootstrap.php"
         colors="true"
         cacheDirectory=".phpunit.cache">
    <testsuites>
        <testsuite name="default">
            <directory>tests</directory>
        </testsuite>
    </testsuites>
</phpunit>
```

**Step 3: Create `tests/bootstrap.php`**

```php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';
```

**Step 4: Update autoload to include tests**

Modify `composer.json`:

```json
{
  "autoload": {
    "psr-4": { "AwesomeList\\": "lib/" }
  },
  "autoload-dev": {
    "psr-4": { "AwesomeList\\Tests\\": "tests/" }
  }
}
```

Run: `composer dump-autoload`

**Step 5: Run the empty test suite**

Run: `vendor/bin/phpunit`
Expected: `No tests executed!` (green, zero tests).

**Step 6: Commit**

```bash
git add composer.json composer.lock phpunit.xml tests/bootstrap.php
git commit -m "chore: add phpunit and symfony/yaml for upcoming data model"
```

---

## Task 1: Entry value object

A read-only DTO representing one list entry as loaded from YAML. Required fields enforced by the constructor.

**Files:**
- Create: `lib/Entry.php`
- Create: `tests/EntryTest.php`

**Step 1: Write failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\Entry;
use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;
use InvalidArgumentException;

final class EntryTest extends TestCase
{
    public function test_it_constructs_with_required_fields(): void
    {
        $entry = new Entry(
            name: 'n98-magerun2',
            url: 'https://github.com/netz98/n98-magerun2',
            description: 'Swiss Army Knife',
            type: EntryType::GithubRepo,
            added: '2018-03-15',
        );

        $this->assertSame('n98-magerun2', $entry->name);
        $this->assertSame(EntryType::GithubRepo, $entry->type);
        $this->assertFalse($entry->pinned);
    }

    public function test_it_rejects_empty_name(): void
    {
        $this->expectException(InvalidArgumentException::class);
        new Entry(name: '', url: 'https://x', description: 'y', type: EntryType::Canonical, added: '2020-01-01');
    }

    public function test_it_allows_null_url_for_archive_type(): void
    {
        $entry = new Entry(
            name: 'Vinai Kopp',
            url: null,
            description: null,
            type: EntryType::Archive,
            added: '2017-01-01',
        );
        $this->assertNull($entry->url);
    }
}
```

**Step 2: Verify it fails**

Run: `vendor/bin/phpunit tests/EntryTest.php`
Expected: `Class "AwesomeList\Entry" not found`.

**Step 3: Create `lib/Entry.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList;

use InvalidArgumentException;

final class Entry
{
    public function __construct(
        public readonly string $name,
        public readonly ?string $url,
        public readonly ?string $description,
        public readonly EntryType $type,
        public readonly string $added,
        public readonly bool $pinned = false,
        public readonly ?string $pinReason = null,
        public readonly array $typeSpecific = [],
    ) {
        if ($name === '') {
            throw new InvalidArgumentException('Entry name must not be empty');
        }
        if ($url === null && $type !== EntryType::Archive) {
            throw new InvalidArgumentException("Entry '$name' of type {$type->value} requires a url");
        }
    }
}
```

**Step 4: Verify it passes**

Run: `vendor/bin/phpunit tests/EntryTest.php`
Expected: 3 tests, 3 assertions, OK.

**Step 5: Commit**

```bash
git add lib/Entry.php tests/EntryTest.php
git commit -m "feat: add Entry value object with required-field validation"
```

---

## Task 2: EntryType enum

**Files:**
- Create: `lib/EntryType.php`
- Create: `tests/EntryTypeTest.php`

**Step 1: Write failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\EntryType;
use PHPUnit\Framework\TestCase;

final class EntryTypeTest extends TestCase
{
    public function test_from_string_resolves_known_types(): void
    {
        $this->assertSame(EntryType::GithubRepo, EntryType::from('github_repo'));
        $this->assertSame(EntryType::Blog, EntryType::from('blog'));
        $this->assertSame(EntryType::Event, EntryType::from('event'));
    }

    public function test_covers_all_nine_types(): void
    {
        $this->assertCount(9, EntryType::cases());
    }
}
```

**Step 2: Verify failure**

Run: `vendor/bin/phpunit tests/EntryTypeTest.php`
Expected: `Class "AwesomeList\EntryType" not found`.

**Step 3: Implement `lib/EntryType.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList;

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
}
```

**Step 4: Verify it passes**

Run: `vendor/bin/phpunit tests/EntryTypeTest.php`
Expected: 2 tests, OK.

**Step 5: Commit**

```bash
git add lib/EntryType.php tests/EntryTypeTest.php
git commit -m "feat: add EntryType enum covering all 9 adapter types"
```

---

## Task 3: YAML entry loader

Reads a YAML file into `Entry[]`.

**Files:**
- Create: `lib/YamlEntryLoader.php`
- Create: `tests/YamlEntryLoaderTest.php`
- Create: `tests/fixtures/entries/sample.yml`

**Step 1: Create the fixture `tests/fixtures/entries/sample.yml`**

```yaml
- name: n98-magerun2
  url: https://github.com/netz98/n98-magerun2
  description: The CLI Swiss Army Knife for Magento 2.
  type: github_repo
  added: 2018-03-15
- name: Magento Developer Documentation
  url: http://devdocs.magento.com/
  description: Official Developer Documentation.
  type: canonical
  added: 2016-01-01
  pinned: true
  pin_reason: Canonical Adobe resource — never auto-retires.
```

**Step 2: Write failing test `tests/YamlEntryLoaderTest.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\EntryType;
use AwesomeList\YamlEntryLoader;
use PHPUnit\Framework\TestCase;

final class YamlEntryLoaderTest extends TestCase
{
    public function test_it_loads_entries_from_a_yaml_file(): void
    {
        $entries = (new YamlEntryLoader())->load(__DIR__ . '/fixtures/entries/sample.yml');

        $this->assertCount(2, $entries);
        $this->assertSame('n98-magerun2', $entries[0]->name);
        $this->assertSame(EntryType::GithubRepo, $entries[0]->type);
        $this->assertTrue($entries[1]->pinned);
        $this->assertSame('Canonical Adobe resource — never auto-retires.', $entries[1]->pinReason);
    }
}
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/YamlEntryLoaderTest.php`
Expected: `Class "AwesomeList\YamlEntryLoader" not found`.

**Step 4: Implement `lib/YamlEntryLoader.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList;

use Symfony\Component\Yaml\Yaml;
use RuntimeException;

final class YamlEntryLoader
{
    /** @return Entry[] */
    public function load(string $path): array
    {
        if (!is_file($path)) {
            throw new RuntimeException("YAML file not found: $path");
        }
        $rows = Yaml::parseFile($path) ?? [];
        if (!is_array($rows)) {
            throw new RuntimeException("Expected a list at the root of $path");
        }

        return array_map(
            fn(array $row): Entry => new Entry(
                name:         (string) $row['name'],
                url:          $row['url'] ?? null,
                description:  $row['description'] ?? null,
                type:         EntryType::from($row['type']),
                added:        (string) $row['added'],
                pinned:       (bool) ($row['pinned'] ?? false),
                pinReason:    $row['pin_reason'] ?? null,
                typeSpecific: array_diff_key($row, array_flip([
                    'name', 'url', 'description', 'type', 'added', 'pinned', 'pin_reason',
                ])),
            ),
            $rows,
        );
    }
}
```

**Step 5: Verify it passes**

Run: `vendor/bin/phpunit tests/YamlEntryLoaderTest.php`
Expected: 1 test, OK.

**Step 6: Commit**

```bash
git add lib/YamlEntryLoader.php tests/YamlEntryLoaderTest.php tests/fixtures/
git commit -m "feat: YamlEntryLoader reads a YAML file into Entry[]"
```

---

## Task 4: Sidecar state loader

Phase 1 reads the sidecar but never writes to it — the file may be absent or empty. Provides the API the renderer will use in Phase 2.

**Files:**
- Create: `lib/SidecarState.php`
- Create: `tests/SidecarStateTest.php`
- Create: `tests/fixtures/state/enrichment.sample.json`

**Step 1: Create fixture `tests/fixtures/state/enrichment.sample.json`**

```json
{
  "https://github.com/netz98/n98-magerun2": {
    "last_checked": "2026-04-19T02:00:00Z",
    "signals": {
      "vitality_hot": true,
      "actively_maintained": true,
      "graveyard_candidate": false
    }
  }
}
```

**Step 2: Write failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests;

use AwesomeList\SidecarState;
use PHPUnit\Framework\TestCase;

final class SidecarStateTest extends TestCase
{
    public function test_it_returns_empty_array_for_missing_file(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/nope.json');
        $this->assertSame([], $state->forUrl('https://example.com'));
    }

    public function test_it_returns_signals_for_a_known_url(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/enrichment.sample.json');
        $signals = $state->signalsFor('https://github.com/netz98/n98-magerun2');
        $this->assertTrue($signals['vitality_hot']);
        $this->assertFalse($signals['graveyard_candidate']);
    }

    public function test_it_returns_null_for_unknown_url(): void
    {
        $state = SidecarState::loadOrEmpty(__DIR__ . '/fixtures/state/enrichment.sample.json');
        $this->assertNull($state->signalsFor('https://unknown.example'));
    }
}
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/SidecarStateTest.php`
Expected: `Class "AwesomeList\SidecarState" not found`.

**Step 4: Implement `lib/SidecarState.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList;

final class SidecarState
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

    public function forUrl(string $url): array
    {
        return $this->byUrl[$url] ?? [];
    }

    public function signalsFor(string $url): ?array
    {
        return $this->byUrl[$url]['signals'] ?? null;
    }
}
```

**Step 5: Verify it passes**

Run: `vendor/bin/phpunit tests/SidecarStateTest.php`
Expected: 3 tests, OK.

**Step 6: Commit**

```bash
git add lib/SidecarState.php tests/SidecarStateTest.php tests/fixtures/state/
git commit -m "feat: SidecarState exposes enrichment signals keyed by url"
```

---

## Task 5: Badge renderer helper

Produces the inline 🔥/🫡 string given sidecar signals. Phase 1 behaviour: empty string unless the sidecar explicitly sets the flags (which it won't in Phase 1 production data — but tests still cover the behaviour so Phase 2 can rely on it).

**Files:**
- Create: `lib/Rendering/BadgeRenderer.php`
- Create: `tests/Rendering/BadgeRendererTest.php`

**Step 1: Write failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Rendering;

use AwesomeList\Rendering\BadgeRenderer;
use PHPUnit\Framework\TestCase;

final class BadgeRendererTest extends TestCase
{
    public function test_no_signals_yields_empty_string(): void
    {
        $this->assertSame('', (new BadgeRenderer())->render(null));
        $this->assertSame('', (new BadgeRenderer())->render([]));
    }

    public function test_vitality_hot_yields_fire(): void
    {
        $this->assertSame(' 🔥', (new BadgeRenderer())->render(['vitality_hot' => true]));
    }

    public function test_both_signals_yield_both_badges_in_stable_order(): void
    {
        $this->assertSame(
            ' 🔥 🫡',
            (new BadgeRenderer())->render([
                'vitality_hot' => true,
                'actively_maintained' => true,
            ])
        );
    }
}
```

**Step 2: Verify failure**

Run: `vendor/bin/phpunit tests/Rendering/BadgeRendererTest.php`
Expected: `Class "AwesomeList\Rendering\BadgeRenderer" not found`.

**Step 3: Implement `lib/Rendering/BadgeRenderer.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Rendering;

final class BadgeRenderer
{
    public function render(?array $signals): string
    {
        if (!$signals) {
            return '';
        }
        $badges = [];
        if (!empty($signals['vitality_hot'])) {
            $badges[] = '🔥';
        }
        if (!empty($signals['actively_maintained'])) {
            $badges[] = '🫡';
        }
        return $badges === [] ? '' : ' ' . implode(' ', $badges);
    }
}
```

**Step 4: Verify it passes**

Run: `vendor/bin/phpunit tests/Rendering/BadgeRendererTest.php`
Expected: 3 tests, OK.

**Step 5: Commit**

```bash
git add lib/Rendering/BadgeRenderer.php tests/Rendering/BadgeRendererTest.php
git commit -m "feat: BadgeRenderer emits vitality and maintained emoji"
```

---

## Task 6: New `YamlEntryList` parser

Implements existing `ParserInterface`. Glues the loader + sidecar + badge renderer into the `parseToMarkdown()` contract used by `MarkdownGenerator`.

**Files:**
- Create: `lib/Parser/YamlEntryList.php`
- Create: `tests/Parser/YamlEntryListTest.php`

**Step 1: Write failing test**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Tests\Parser;

use AwesomeList\Parser\YamlEntryList;
use PHPUnit\Framework\TestCase;

final class YamlEntryListTest extends TestCase
{
    public function test_it_emits_one_markdown_line_per_entry(): void
    {
        $parser = new YamlEntryList();
        $parser->setFilename(__DIR__ . '/../fixtures/entries/sample.yml');
        $markdown = $parser->parseToMarkdown();

        $this->assertStringContainsString(
            '- [n98-magerun2](https://github.com/netz98/n98-magerun2) - The CLI Swiss Army Knife for Magento 2.',
            $markdown,
        );
        $this->assertStringContainsString(
            '- [Magento Developer Documentation](http://devdocs.magento.com/) - Official Developer Documentation.',
            $markdown,
        );
    }

    public function test_entries_without_description_omit_the_dash(): void
    {
        $parser = new YamlEntryList();
        $parser->setFilename(__DIR__ . '/../fixtures/entries/no-description.yml');
        $markdown = $parser->parseToMarkdown();
        $this->assertStringContainsString('- [Foo](https://foo.example)' . "\n", $markdown);
    }
}
```

**Step 2: Create additional fixture `tests/fixtures/entries/no-description.yml`**

```yaml
- name: Foo
  url: https://foo.example
  type: vendor_site
  added: 2024-01-01
```

**Step 3: Verify failure**

Run: `vendor/bin/phpunit tests/Parser/YamlEntryListTest.php`
Expected: `Class "AwesomeList\Parser\YamlEntryList" not found`.

**Step 4: Implement `lib/Parser/YamlEntryList.php`**

```php
<?php declare(strict_types=1);
namespace AwesomeList\Parser;

use AwesomeList\Entry;
use AwesomeList\Rendering\BadgeRenderer;
use AwesomeList\SidecarState;
use AwesomeList\YamlEntryLoader;

final class YamlEntryList implements ParserInterface
{
    private string $filename;
    private readonly YamlEntryLoader $loader;
    private readonly BadgeRenderer $badges;
    private readonly string $sidecarPath;

    public function __construct(
        ?YamlEntryLoader $loader = null,
        ?BadgeRenderer $badges = null,
        ?string $sidecarPath = null,
    ) {
        $this->loader      = $loader ?? new YamlEntryLoader();
        $this->badges      = $badges ?? new BadgeRenderer();
        $this->sidecarPath = $sidecarPath ?? __DIR__ . '/../../state/enrichment.json';
    }

    public function setFilename(string $filename): void
    {
        $this->filename = $filename;
    }

    public function parseToMarkdown(): string
    {
        $entries = $this->loader->load($this->filename);
        $state   = SidecarState::loadOrEmpty($this->sidecarPath);

        $lines = [];
        foreach ($entries as $entry) {
            $lines[] = $this->formatLine($entry, $state);
        }
        return implode("\n", $lines);
    }

    private function formatLine(Entry $entry, SidecarState $state): string
    {
        $link = $entry->url !== null ? "[{$entry->name}]({$entry->url})" : $entry->name;
        $badges = $entry->url !== null
            ? $this->badges->render($state->signalsFor($entry->url))
            : '';
        $line = "- {$link}{$badges}";
        if ($entry->description !== null && $entry->description !== '') {
            $line .= " - {$entry->description}";
        }
        return $line;
    }
}
```

**Step 5: Verify it passes**

Run: `vendor/bin/phpunit tests/Parser/YamlEntryListTest.php`
Expected: 2 tests, OK. Re-run full suite: `vendor/bin/phpunit` — expected all green.

**Step 6: Commit**

```bash
git add lib/Parser/YamlEntryList.php tests/Parser/ tests/fixtures/entries/no-description.yml
git commit -m "feat: YamlEntryList parser emits markdown list from YAML + sidecar"
```

---

## Task 7: Strangler step — import README body into `content/main.md`

Make the generator own the entire document. After this task, `generate.php` can fully regenerate the README; Frontends still comes from the old CSV.

**Files:**
- Modify: `content/main.md`
- Modify: `generate.php` (if needed)

**Step 1: Copy current README into `content/main.md`, preserving the single existing tag**

Open `README.md`. Locate the `## Front-ends` section. The remaining content of `README.md` (everything *except* the entries between that heading and the next `## Tools` heading) must end up in `content/main.md`. The Frontends section becomes the existing tag:

```markdown
## Front-ends

The storefront of Magento 2 can be styled in numerous ways:

{% file=content/frontend.csv parser="AwesomeList\Parser\GenericCsvList" %}
```

Everything else (intro, TOC, Tools, Extensions, Blogs, Events, License, …) is raw Markdown copied verbatim from the current `README.md`.

**Step 2: Change `generate.php` to write `README.md` directly, not `README.md.new`**

The old `.new` dance was a safety net when the generator was partial. Now it owns the whole doc, so writing `README.md` is correct.

```php
file_put_contents(__DIR__ . '/README.md', $contents);
```

**Step 3: Regenerate and diff**

```bash
php generate.php
git diff README.md
```

Expected: no substantive diff. Small whitespace differences from the CSV parser are acceptable; anything larger means `content/main.md` is missing content.

**Step 4: Commit**

```bash
git add content/main.md generate.php README.md
git commit -m "refactor: move README body into content/main.md as generator source"
```

---

## Task 8: Migrate Frontends CSV → YAML

**Files:**
- Create: `data/frontends.yml`
- Delete: `content/frontend.csv`
- Modify: `content/main.md` (replace the CSV tag with the YAML tag)

**Step 1: Create `data/frontends.yml`**

Translate each row of `content/frontend.csv`. Types: `Luma`, `Adobe PWA Studio`, `Hyvä` are described on vendor/doc pages → `canonical`; the rest point at GitHub repos or commercial theme sites → `github_repo` or `vendor_site` as appropriate.

```yaml
- name: Magento Luma
  url: https://developer.adobe.com/commerce/frontend-core/guide/
  description: "Magento 2's default demo theme (extends Magento/blank). The name also refers to the whole Luma stack: XML layout + blocks/containers + PHTML templates, enriched with LESS-compiled CSS and RequireJS/KnockoutJS/jQuery."
  type: canonical
  added: 2016-01-01
  pinned: true
  pin_reason: Canonical Adobe frontend reference
- name: Adobe PWA Studio
  url: https://developer.adobe.com/commerce/pwa-studio/
  description: "Adobe's headless React frontend. GraphQL client; offers Venia theme, Peregrine hooks, Buildpack (Webpack) and UPWARD (SSR/image middleware)."
  type: canonical
  added: 2018-05-01
  pinned: true
  pin_reason: Canonical Adobe PWA reference
- name: Hyvä
  url: https://hyva.io/
  description: "Luma replacement using TailwindCSS and AlpineJS. Commercial license. Active compatibility-module ecosystem."
  type: vendor_site
  added: 2021-04-01
- name: Alokai
  url: https://github.com/vuestorefront/vue-storefront
  description: "Formerly Vue Storefront — headless frontend framework."
  type: github_repo
  added: 2018-01-01
- name: ScandiPWA
  url: https://github.com/scandipwa/scandipwa
  description: "React/Redux PWA theme for Magento 2.3+."
  type: github_repo
  added: 2019-02-01
- name: Breeze Evolution
  url: https://breezefront.com/themes
  description: "Lightweight Luma-compatible theme targeting 100 PageSpeed."
  type: vendor_site
  added: 2023-01-01
- name: Front-Commerce
  url: https://www.front-commerce.com/
  description: "French PWA front-end solution for Magento."
  type: vendor_site
  added: 2019-06-01
```

*(Note: `DEITY` in the old CSV has no URL. Drop it — entries without URLs only make sense for `archive` type.)*

**Step 2: Replace the CSV tag in `content/main.md`**

Old:
```
{% file=content/frontend.csv parser="AwesomeList\Parser\GenericCsvList" %}
```

New:
```
{% file=data/frontends.yml parser="AwesomeList\Parser\YamlEntryList" %}
```

**Step 3: Delete the old CSV**

```bash
git rm content/frontend.csv
```

**Step 4: Regenerate and verify**

```bash
php generate.php
git diff README.md
```

Expected: the Frontends section now lists the entries from `data/frontends.yml` in YAML order, with descriptions. Compare against the previous Frontends section — should be content-equivalent.

**Step 5: Commit**

```bash
git add data/frontends.yml content/main.md README.md
git commit -m "refactor(frontends): migrate from CSV to YAML entries"
```

---

## Task 9: JSON Schema for entries

A Composer script validates every YAML file in `data/` against the schema. Catches malformed entries at PR time.

**Files:**
- Create: `schemas/entry.schema.json`
- Create: `bin/validate-data.php`
- Modify: `composer.json` (scripts section)
- Add dependency: `justinrainbow/json-schema`

**Step 1: Add dependency**

Run: `composer require --dev justinrainbow/json-schema:^5`

**Step 2: Create `schemas/entry.schema.json`** (draft-07)

```json
{
  "$schema": "http://json-schema.org/draft-07/schema#",
  "title": "Awesome Magento 2 Entry List",
  "type": "array",
  "items": {
    "type": "object",
    "required": ["name", "type", "added"],
    "properties": {
      "name":        { "type": "string", "minLength": 1 },
      "url":         { "type": ["string", "null"], "format": "uri" },
      "description": { "type": ["string", "null"] },
      "type":        {
        "type": "string",
        "enum": ["github_repo", "blog", "packagist_pkg", "event", "youtube_playlist", "course", "vendor_site", "archive", "canonical"]
      },
      "added":       { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2}$" },
      "pinned":      { "type": "boolean" },
      "pin_reason":  { "type": "string" },
      "next_date":   { "type": "string", "pattern": "^\\d{4}-\\d{2}-\\d{2}$" },
      "recurring":   { "type": "string", "enum": ["annual", "biennial", "one-off"] },
      "location":    { "type": "object" },
      "organizers":  { "type": "array", "items": { "type": "string" } },
      "channel_id":  { "type": "string" },
      "section":    { "type": "string" },
      "year":       { "type": "integer" }
    },
    "additionalProperties": false,
    "allOf": [
      {
        "if": { "properties": { "type": { "not": { "const": "archive" } } } },
        "then": { "required": ["url"] }
      }
    ]
  }
}
```

**Step 3: Create `bin/validate-data.php`**

```php
#!/usr/bin/env php
<?php declare(strict_types=1);
require_once __DIR__ . '/../vendor/autoload.php';

use JsonSchema\Validator;
use Symfony\Component\Yaml\Yaml;

$schema = json_decode((string) file_get_contents(__DIR__ . '/../schemas/entry.schema.json'));
$files  = [];
$dataDir = __DIR__ . '/../data';
$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dataDir));
foreach ($iterator as $file) {
    if ($file->isFile() && $file->getExtension() === 'yml') {
        $files[] = $file->getPathname();
    }
}

$exit = 0;
foreach ($files as $file) {
    $data      = Yaml::parseFile($file);
    $validator = new Validator();
    $validator->validate($data, $schema, \JsonSchema\Constraints\Constraint::CHECK_MODE_TYPE_CAST);
    if (!$validator->isValid()) {
        $exit = 1;
        echo "✗ $file\n";
        foreach ($validator->getErrors() as $err) {
            echo "  [{$err['property']}] {$err['message']}\n";
        }
    } else {
        echo "✓ $file\n";
    }
}
exit($exit);
```

**Step 4: Add Composer script**

In `composer.json`:

```json
{
  "scripts": {
    "validate-data": "php bin/validate-data.php",
    "test":          "phpunit"
  }
}
```

**Step 5: Run**

Run: `composer validate-data`
Expected: `✓ data/frontends.yml`, exit 0.

**Step 6: Commit**

```bash
git add composer.json composer.lock schemas/entry.schema.json bin/validate-data.php
git commit -m "feat: JSON Schema + composer validate-data for data/*.yml"
```

---

## Task 10: GitHub Action `regenerate.yml`

On push to master touching `content/**`, `data/**`, `lib/**`, or `state/**`, regenerate `README.md` and commit it back.

**Files:**
- Create: `.github/workflows/regenerate.yml`

**Step 1: Create workflow**

```yaml
name: Regenerate README

on:
  push:
    branches: [master]
    paths:
      - 'content/**'
      - 'data/**'
      - 'lib/**'
      - 'state/**'
      - 'generate.php'
  workflow_dispatch:

concurrency:
  group: regenerate
  cancel-in-progress: false

jobs:
  regenerate:
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

      - run: composer validate-data

      - run: vendor/bin/phpunit

      - run: php generate.php

      - name: Commit regenerated README
        run: |
          if git diff --quiet README.md; then
            echo "README unchanged"
            exit 0
          fi
          git config user.name  'github-actions[bot]'
          git config user.email 'github-actions[bot]@users.noreply.github.com'
          git add README.md
          git commit -m 'chore: regenerate README [skip ci]'
          git push
```

**Step 2: Verify locally that `composer validate-data` and `vendor/bin/phpunit` both pass**

Run: `composer validate-data && vendor/bin/phpunit`
Expected: both green.

**Step 3: Commit**

```bash
git add .github/workflows/regenerate.yml
git commit -m "ci: regenerate README on content/data/lib changes"
```

---

## Task 11: Update `CLAUDE.md` and `contributing.md`

**Files:**
- Modify: `CLAUDE.md`
- Modify: `contributing.md`

**Step 1: Update `CLAUDE.md`**

Replace the "Editing entries" and "Content architecture" sections to reflect the new flow: entries live in `data/**/*.yml`, sections migrate strangler-style from `content/main.md` to `{% file=data/<category>.yml parser="AwesomeList\Parser\YamlEntryList" %}` tags, `composer validate-data` catches schema errors, `vendor/bin/phpunit` runs tests. Remove the warning about most sections being hand-maintained in README.md — that's now partially obsolete (Frontends is data-driven; rest still in `content/main.md` prose pending Phase 4 migration).

**Step 2: Update `contributing.md`**

Add a section: "Adding an entry (YAML flow)" with:

```markdown
## Adding an entry (YAML flow)

Once a category has been migrated (see `data/`), contributing is editing one YAML file:

```yaml
- name: Your Project
  url: https://github.com/you/your-project
  description: One-line, concise.
  type: github_repo
  added: 2026-04-19
```

Required: `name`, `url` (except `type: archive`), `description`, `type`, `added`. Valid `type` values: `github_repo`, `blog`, `packagist_pkg`, `event`, `youtube_playlist`, `course`, `vendor_site`, `archive`, `canonical`.

Run `composer validate-data` to verify your YAML. CI regenerates `README.md` on merge; do not edit it directly.
```

**Step 3: Commit**

```bash
git add CLAUDE.md contributing.md
git commit -m "docs: update contributor + agent docs for YAML entry flow"
```

---

## Task 12: End-to-end verification

**Step 1: Fresh regenerate from clean state**

```bash
rm -f README.md
php generate.php
git status
```

Expected: `README.md` recreated; no other files changed.

**Step 2: Compare Frontends section against `master` baseline**

```bash
git show master:README.md | sed -n '/^## Front-ends/,/^## Tools/p' > /tmp/before.txt
sed -n '/^## Front-ends/,/^## Tools/p' README.md > /tmp/after.txt
diff /tmp/before.txt /tmp/after.txt
```

Expected: content-equivalent — links and descriptions match. Minor formatting differences acceptable; semantic content must be preserved.

**Step 3: Run the full test suite one more time**

```bash
vendor/bin/phpunit
composer validate-data
```

Expected: all green.

**Step 4: If anything diverges unexpectedly, stop and investigate**

Do not paper over differences. Each difference is either a bug in the generator (fix it) or a deliberate improvement (document it in the commit message).

---

## Task 13: Open pull request

**Step 1: Push the branch**

```bash
git push -u origin design/auto-update-pipeline
```

**Step 2: Open PR against `master`**

```bash
gh pr create --title "Phase 1: Auto-update foundations + Frontends migration" --body "$(cat <<'EOF'
## Summary

Phase 1 of the auto-update pipeline described in `docs/plans/2026-04-19-auto-update-design.md`:

- Adds YAML-based entry data model (`data/**/*.yml`) with `Entry` value object and `EntryType` enum (9 types).
- Adds `SidecarState` reader for `state/enrichment.json` (empty in Phase 1 — used by Phase 2 enrichment).
- Adds new `YamlEntryList` parser that plugs into the existing tag-templating engine.
- Strangler migration: `content/main.md` now owns the whole README body; `generate.php` writes `README.md` directly.
- Migrates the Frontends section from CSV to YAML as a proof of the full loop.
- Adds JSON Schema validation via `composer validate-data`.
- Adds GitHub Action `regenerate.yml` that rebuilds the README on `content/`, `data/`, `lib/` changes.
- Updates `CLAUDE.md` and `contributing.md` for the new flow.

Follows the 2022 roadmap in #102 and keeps #105/#108 achievable in Phase 2.

## Out of scope

Enrichment, discovery, graveyard, 🔥/🫡 badges (rendered but always empty), and migrating categories other than Frontends — all Phase 2–4.

## Test plan

- [ ] `vendor/bin/phpunit` green
- [ ] `composer validate-data` green
- [ ] `php generate.php` produces a README whose Frontends section is content-equivalent to the previous hand-written one
- [ ] CI `regenerate.yml` runs on merge and commits no change (README already up to date)
EOF
)"
```

---

## Phase 2+ (separate plans)

Once Phase 1 is merged, follow-up plans in `docs/plans/`:

- **Phase 2 — Enrichment core**: `EnrichmentAdapter` interface, `AdapterFactory`, `github_repo` adapter + unit tests against recorded HTTP fixtures, `enrich.yml` workflow, graveyard routing in the renderer, 🔥/🫡 thresholds and category grouping for the top-10% computation.
- **Phase 3 — Discovery**: `discover.yml` workflow, weekly candidates issue template, `accept-candidate.yml` triggered by issue edits, `candidates.log.json` dedup.
- **Phase 4a — Remaining adapters**: blog (RSS autodiscovery), packagist_pkg, event (HTTP + year-regex), youtube_playlist (YouTube Data API), course/vendor_site/canonical (HTTP liveness).
- **Phase 4b — Content migration**: Tools, Extensions subcategories, Blogs, Other Awesome Lists, Platforms, Official Resources, Localization, Learning, Events + Meet Magento, Masters, Trustworthy Developers. One commit per category with a before/after diff.
- **Phase 5 — iCal/JSON feeds** (per #105): emit `events.ical` and `events.json` from `data/events/**` during regeneration.
