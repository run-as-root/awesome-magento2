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
- Sidecar state at `state/enrichment.json` holds bot-derived signals keyed by entry URL. Written by the `enrich.yml` workflow (Mondays 02:00 UTC) — do not edit by hand. Read by `YamlEntryList` to render 🔥 (top-10% stars in category), 🫡 (actively maintained), and a graveyard `<details>` block for archived/stale entries.
- Parsers live in `lib/Parser/` and implement `AwesomeList\Parser\ParserInterface` (`setFilename` + `parseToMarkdown`). `YamlEntryList` is the one to use for new sections.
- PHPUnit tests in `tests/` cover the loader, parser, and generator. Run them with `composer test` (or `vendor/bin/phpunit`).
- GitHub Action `.github/workflows/regenerate.yml` re-runs `php generate.php` and commits the updated `README.md` on push to `master` whenever `content/`, `data/`, `lib/`, `state/`, or `generate.php` changes.

Only the "Frontends" section has been migrated to `data/frontends.yml` so far. The remaining sections (Tools, Open Source Extensions, Blogs, Events, License, etc.) are still raw Markdown inside `content/main.md` and will move to YAML in Phase 4b. When editing those sections for now, edit `content/main.md` directly.

## Editing entries

For migrated categories (anything referenced by a `{% file=data/...yml %}` tag in `content/main.md`), add or edit entries in the corresponding YAML file — see the "Adding an entry (YAML flow)" section of `contributing.md` for the required fields and an example. For sections still in raw Markdown, follow the classic format: `[Name](URL) - Description`, concise, loosely alphabetized by topic.

Extension submissions must meet the quality gates in `contributing.md`: the upstream project needs a README covering purpose, install steps, supported Magento versions, and requirements, plus at least one stable release. Flag or reject PRs that don't.

## CI

Two pipelines run:

- `.github/workflows/regenerate.yml` — regenerates `README.md` from `content/` + `data/` on push to `master`.
- `.gitlab-ci.yml` — pulls two remote jobs from `run-as-root/gitlab-pipeline-templates`: a markdown spellcheck and `awesome-bot` (link checker).

Local verification before pushing: `composer validate-data` (schema) and `composer test` (PHPUnit).
