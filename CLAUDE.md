# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## What this repo is

A curated "Awesome" list of Magento 2 extensions and resources. The public artifact is `README.md`; the PHP code in `lib/` plus the templated sources in `content/` exist only to generate portions of that README.

## Generating the README

```bash
composer install        # one-time: pulls in the PSR-4 autoloader for AwesomeList\
php generate.php        # renders content/main.md → README.md.new
```

`generate.php` intentionally writes to `README.md.new`, not `README.md`. Diff the output against `README.md` and copy over manually when satisfied — the generator does not overwrite the published file.

PHP 8.1+ is required (`composer.json`).

## Content architecture

The system is a tiny templating engine, partially adopted:

- `content/main.md` is the template. Custom tags of the form `{% file=foo.csv parser="AwesomeList\Parser\GenericCsvList" %}` are expanded into Markdown.
- `lib/MarkdownGenerator.php` regex-scans for those tags, delegates to `lib/ParserFactory.php`, and splices the parser's output back into the document.
- Parsers implement `AwesomeList\Parser\ParserInterface` (`setFilename` + `parseToMarkdown`). Today only `GenericCsvList` exists (expects CSV rows of `name,url,description`).
- To add a new data-driven section: drop a data file in `content/`, write a parser in `lib/Parser/` implementing `ParserInterface`, and reference it with a `{% %}` tag in `content/main.md`.

**Important mismatch to be aware of:** `content/main.md` currently covers only the "Frontend" section. The rest of `README.md` (Tools, Open Source Extensions, Blogs, Education, Platforms, Official Resources, etc.) is hand-maintained directly in `README.md` and has *not* been migrated into the templating system. Editing `content/main.md` alone will not regenerate those sections — if a PR touches them, edit `README.md` directly.

## Editing entries

Follow `contributing.md`: `[Name](URL) - Description`, kept concise. Sections are alphabetized loosely by topic, not strictly. When adding extensions, the contribution guide requires the upstream project to have a README covering purpose/install/supported Magento versions/requirements, plus at least one stable release — reject or flag PRs that don't meet this.

## CI

`.gitlab-ci.yml` pulls two remote jobs from `run-as-root/gitlab-pipeline-templates`: a markdown spellcheck and `awesome-bot` (link checker). There is no PHP test suite — changes to `lib/` are validated by running `generate.php` and diffing the output.
