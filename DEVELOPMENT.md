# Development details of this repository

### Overview
`README.md` is a build artifact. The source of truth is split in two:

- `content/main.md` — hand-written prose (intro, TOC, category descriptions, license).
- `data/**/*.yml` — structured entries for migrated sections (currently: `data/frontends.yml`).

`content/main.md` embeds data via custom tags:

    {% file=data/frontends.yml parser="AwesomeList\Parser\YamlEntryList" %}

`generate.php` expands each tag via `lib/MarkdownGenerator.php`, which delegates to the parser named in the tag. Paths are resolved relative to the repo root. The expanded output is written directly to `README.md`.

Entries in `data/**/*.yml` are validated against `schemas/entry.schema.json` — see `contributing.md` for the required fields.

An enrichment sidecar at `state/enrichment.json` is maintained by the `.github/workflows/enrich.yml` workflow (weekly, Mondays 02:00 UTC). It holds per-entry signals (GitHub stars, last commit, last release, archived) which `YamlEntryList` reads to render 🔥/🫡 badges and a graveyard section.

### Generating locally
```bash
composer install
composer test                       # phpunit
composer validate-data              # JSON Schema check on data/**/*.yml
composer enrich                     # optional; needs GITHUB_TOKEN for >60 req/h
php generate.php                    # writes README.md
```