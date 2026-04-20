# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A curated "Awesome" list of Magento 2 extensions and resources. The public artifact is `README.md`; the PHP code in `lib/`, the templated sources in `content/`, and the structured data in `data/` exist only to generate that README.

## Generating the README

```bash
composer install        # one-time: pulls the PSR-4 autoloader + Symfony YAML + PHPUnit + json-schema
php generate.php        # renders content/main.md → README.md
```

`generate.php` writes `README.md` directly. CI regenerates it on merges to `master`, so in practice you rarely run this by hand — edit `data/` or `content/main.md` and let the workflow publish.

PHP 8.1+ is required (`composer.json`).

## Content architecture

The repo is moving strangler-style from raw hand-maintained Markdown to a data + template split. Today both coexist:

- `content/main.md` owns the full README body. Plain Markdown passes through untouched; custom tags of the form `{% file=data/<category>.yml parser="AwesomeList\Parser\YamlEntryList" %}` are expanded inline.
- `lib/MarkdownGenerator.php` regex-scans for those tags, delegates to `lib/ParserFactory.php`, and splices each parser's Markdown output back into the document. Tag `file=` paths resolve relative to the repo root.
- Entries live in `data/**/*.yml`, one file per category. Each entry is a YAML map with `name`, `url`, `description`, `type`, `added` — see `contributing.md` for the schema.
- JSON Schema at `schemas/entry.schema.json` defines the contract. Run `composer validate-data` to catch malformed YAML or schema violations before generating.
- Sidecar state at `state/enrichment.json` holds bot-derived signals keyed by entry URL. Written by the `enrich.yml` workflow (Mondays 02:00 UTC) — do not edit by hand. Read by `YamlEntryList` to render 🔥 (top-10% GitHub stars *or* packagist monthly downloads per category), 🫡 (actively maintained per type-specific rules), and a graveyard `<details>` block for archived/stale/broken entries. Enrichment adapters live in `lib/Enrichment/`, one per type: `github_repo`, `packagist_pkg`, `blog` (RSS autodiscovery), `event` (year-regex), `youtube_playlist` (YouTube Data API v3), `vendor_site` / `course` / `canonical` (HTTP liveness via a single `LivenessAdapter`), and `archive` (explicit no-op). Set `YOUTUBE_API_KEY` as a repo secret to enable YouTube enrichment; if unset the YouTube adapter silently skips.
- Parsers live in `lib/Parser/` and implement `AwesomeList\Parser\ParserInterface` (`setFilename` + `parseToMarkdown`). `YamlEntryList` is the one to use for new sections.
- PHPUnit tests in `tests/` cover the loader, parser, and generator. Run them with `composer test` (or `vendor/bin/phpunit`).
- GitHub Action `.github/workflows/regenerate.yml` re-runs `php generate.php` and commits the updated `README.md` on push to `master` whenever `content/`, `data/`, `lib/`, `state/`, or `generate.php` changes. `.github/workflows/enrich.yml` runs weekly (Mondays 02:00 UTC) and commits the refreshed sidecar + regenerated README in one commit.

Most sections have been migrated to `data/**/*.yml`. Some prose (intro, Ukraine banner, Legend, "What is Magento?", License, Thanks footer) stays in `content/main.md` because it's narrative, not entries.

## Editing entries

For migrated categories (anything referenced by a `{% file=data/...yml %}` tag in `content/main.md`), add or edit entries in the corresponding YAML file — see the "Adding an entry (YAML flow)" section of `contributing.md` for the required fields and an example. For sections still in raw Markdown, follow the classic format: `[Name](URL) - Description`, concise, loosely alphabetized by topic.

Extension submissions must meet the quality gates in `contributing.md`: the upstream project needs a README covering purpose, install steps, supported Magento versions, and requirements, plus at least one stable release. Flag or reject PRs that don't.

## CI

Two pipelines run:

- `.github/workflows/regenerate.yml` — regenerates `README.md` from `content/` + `data/` on push to `master`.
- `.gitlab-ci.yml` — pulls two remote jobs from `run-as-root/gitlab-pipeline-templates`: a markdown spellcheck and `awesome-bot` (link checker).

Local verification before pushing: `composer validate-data` (schema) and `composer test` (PHPUnit).
