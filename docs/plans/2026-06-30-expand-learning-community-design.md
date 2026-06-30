# Design: Expand Learning & Community sections (issue #106)

**Date:** 2026-06-30
**Issue:** https://github.com/run-as-root/awesome-magento2/issues/106
**Status:** Approved

## Context

Issue #106 raised overlap with [aleron75/mageres](https://github.com/aleron75/mageres). Rather than coordinating scope boundaries, the decision is to **expand awesome-magento2** with categories mageres covers well but this repo currently lacks: podcasts, newsletters, and community resources. awesome-magento2 brings quality signals (liveness, activity badges, dead-entry graveyard) that mageres does not have, so the two lists can coexist and serve different audiences.

## New files

```
data/podcasts.yml
data/newsletters.yml
data/community.yml
lib/Enrichment/PodcastAdapter.php
```

## Data model

All new entries use the existing YAML schema (`name`, `url`, `description`, `type`, `added`).

| Category | `type` value | Enrichment behaviour |
|---|---|---|
| Podcasts | `podcast` | NEW adapter — RSS freshness; last episode >12 months → graveyard |
| Newsletters | `blog` | Existing RSS adapter — feed freshness check |
| Communities | `canonical` | Existing — HTTP liveness only |
| Associations | `canonical` | Existing — HTTP liveness only |

## PodcastAdapter

Lives in `lib/Enrichment/` alongside `BlogAdapter`. Behaviour:

- Autodiscovers RSS feed from podcast homepage via `<link rel="alternate" type="application/rss+xml">`
- Parses most recent `<pubDate>` from feed
- Writes `last_post_at` to `state/enrichment.json` (same key as BlogAdapter)
- Actively maintained rule (`🫡`): last episode within 12 months

Implementation mirrors `BlogAdapter` exactly — only the staleness threshold and type name differ.

## content/main.md additions

Three new sections added after the existing `## Blogs` block:

```markdown
## Podcasts

{% file=data/podcasts.yml parser="AwesomeList\Parser\YamlEntryList" %}

## Newsletters

{% file=data/newsletters.yml parser="AwesomeList\Parser\YamlEntryList" %}

## Community

{% file=data/community.yml parser="AwesomeList\Parser\YamlEntryList" %}
```

## Content import strategy

Curated one-time import from mageres. Inclusion bar:

- URL resolves (liveness check before adding)
- Magento 2 focused (not M1-only)
- English-language (primary scope)
- Podcast/newsletter: has had activity within ~2 years

Target inventory:
- **Podcasts**: ~5–8 entries (Mage Talk, and others with consistent M2 coverage)
- **Newsletters**: ~3–5 entries (Mageres monthly digest, MageTalk newsletter, Mage-OS/Adobe Commerce digests)
- **Community**: ~6–10 entries (MageHero, Magento Stack Exchange, Mage-OS Discord, regional associations)

Each entry hand-verified before committing — no bulk import.

## Schema validator

`podcast` added as a valid `type` value in `schemas/entry.schema.json`.

## Out of scope

- Screencasts, books, certification: already covered adequately by `data/learning.yml`
- Non-English communities: deferred, can be added later via PRs
- Automated discovery for podcasts/newsletters: not planned, community-driven PRs going forward
