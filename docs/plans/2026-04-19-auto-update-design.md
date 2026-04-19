# Design: Auto-updated Awesome Magento 2 List

**Status:** Approved design, pending implementation plan
**Date:** 2026-04-19
**Author:** David Lambauer (w/ Claude)

## Summary

Convert `awesome-magento2` from a hand-curated Markdown file into a data-driven, automatically-maintained list. Entries live as structured YAML; a scheduled pipeline enriches them with signals from GitHub, Packagist, RSS, and YouTube; stale/archived entries auto-retire into a graveyard; a discovery bot surfaces new candidate entries via a weekly checkbox-driven issue for one-click curator approval. The `README.md` becomes a build artifact regenerated deterministically from the data.

## Prior art

Issue [#102 (Jisse Reitsma, 2022)](https://github.com/run-as-root/awesome-magento2/issues/102) proposed splitting the single Markdown file into structured resources parsed by a build tool — the existing `content/frontend.csv` + `generate.php` is the partial realisation. This design is the completion of that roadmap, extended with automated enrichment, discovery, and retirement.

Directly addresses:
- #102 — dynamic Markdown build
- #105 — reusable event data (now powers iCal/JSON feeds as a side artifact)
- #108 — 🔥 / 🫡 vitality emoji indicators
- #103 — CONTRIBUTING.md update is implicit

## Scope

Chose **full-pipeline coverage** (option C + (a) during brainstorming): every category, including events, Masters, and vendor sites, is part of the data model and regeneration pipeline. Sections that can't be meaningfully enriched (Masters 2017, Official Resources) use `archive` / `canonical` adapter types with no retirement rules — they participate in rendering but not auto-retirement.

### Out of scope
- LinkedIn signal mining (no API, ToS-hostile)
- X/Twitter trending/sentiment (too noisy, star velocity is the good proxy)
- Magento Marketplace discovery (no programmatic API)
- Analytics dashboard or web UI
- Auto-creation of new subcategories (bot drops ambiguous entries in `_triage.yml`; human decides taxonomy)
- i18n of this repo

## Architecture

```
                     ┌──────────────────────────────┐
  Weekly cron ──▶    │   GitHub Actions workflows   │
                     │                              │
                     │   1. enrich     (signals)    │
                     │   2. discover   (candidates) │
                     │   3. accept-candidate (PR)   │
                     │   4. regenerate (README)     │
                     └──────────────┬───────────────┘
                                    │
               ┌────────────────────┴─────────────────────┐
               ▼                                          ▼
      ┌─────────────────┐                       ┌──────────────────┐
      │  data/**/*.yml  │◀── PRs ──────────     │ state/           │
      │ (human-edited)  │                       │  enrichment.json │
      │ one file per    │                       │  (bot-written,   │
      │ subcategory     │                       │   committed)     │
      └────────┬────────┘                       └────────┬─────────┘
               │                                         │
               └──────────────┬──────────────────────────┘
                              ▼
                    ┌─────────────────────┐
                    │   generate.php      │
                    │   (expanded)        │
                    └──────────┬──────────┘
                               ▼
                        README.md (build artifact)
                      + events.ical + events.json
                      + Graveyard appendix
                      + 🔥 / 🫡 badges inline
```

### Key invariants

1. **Human YAML holds what the curator believes. Sidecar JSON holds what the signals say.** The renderer joins them at build time. Human files stay clean of enrichment churn.
2. **Pin wins over signal.** `pinned: true` suppresses auto-retirement silently.
3. **README is a build artifact** — never hand-edited post-migration. `content/main.md` holds the hand-written prose (intro, Ukraine banner).
4. **CI platform split**: new pipeline on GitHub Actions (repo canonical home is `run-as-root/awesome-magento2` on GitHub). Existing `.gitlab-ci.yml` stays for markdown spellcheck + awesome-bot link check.

## Data model

### File layout

```
data/
├── tools.yml
├── frontends.yml
├── platforms.yml
├── learning.yml
├── developers.yml          # Trustworthy Extension Developers
├── other-lists.yml
├── official-resources.yml
├── blogs/
│   ├── personal.yml
│   ├── company.yml
│   └── other.yml
├── extensions/
│   ├── development-utilities.yml
│   ├── deployment.yml
│   ├── localization.yml
│   ├── search.yml
│   ├── cms.yml
│   ├── marketing.yml
│   ├── adminhtml.yml
│   ├── security.yml
│   ├── payment.yml
│   ├── infrastructure.yml
│   ├── proprietary.yml
│   ├── pwa.yml
│   └── _triage.yml          # auto-discovery holding bay
├── events/
│   ├── mage-events.yml
│   └── meet-magento.yml
└── masters/
    └── 2017.yml

state/
├── enrichment.json          # bot-written, committed
└── candidates.log.json      # discovery dedup history

content/
└── main.md                  # hand-written intro, Ukraine banner, outro
```

### Shared entry schema

Four required fields:

```yaml
- name: n98-magerun2
  url: https://github.com/netz98/n98-magerun2
  description: The CLI Swiss Army Knife for Magento 2.
  type: github_repo
  added: 2018-03-15

  # Optional:
  # pinned: true
  # pin_reason: "feature-complete, still works"
```

### Type-specific extensions

**`event`** (powers iCal/JSON feed per #105):

```yaml
- name: MageUnconference Germany
  url: https://www.mageunconference.org/
  description: A Magento Unconference in Germany.
  type: event
  added: 2018-01-01
  next_date: 2026-10-15
  recurring: annual         # annual | biennial | one-off
  location: { city: Cologne, country: DE }
  organizers: ["Jisse Reitsma"]
```

**`youtube_playlist`**:

```yaml
  channel_id: UCRFDWo7jTlrpEsJxzc7WyPw
```

**`archive`** (Masters 2017, URL-less entries allowed):

```yaml
- name: Vinai Kopp
  type: archive
  section: magento-masters-2017
  year: 2017
```

### Sidecar schema (`state/enrichment.json`)

Keyed by URL. Committed (not `.gitignore`d) so git log serves as an audit trail and offline rebuilds stay deterministic.

```json
{
  "https://github.com/netz98/n98-magerun2": {
    "last_checked": "2026-04-19T02:00:00Z",
    "link_status": "ok",
    "link_status_since": "2020-01-01T00:00:00Z",
    "github": {
      "stars": 2147,
      "stars_90d_delta": 58,
      "last_commit": "2026-04-10T09:23:00Z",
      "last_release": "2026-03-28T00:00:00Z",
      "archived": false,
      "fork": false
    },
    "signals": {
      "vitality_hot": true,
      "actively_maintained": true,
      "graveyard_candidate": false,
      "graveyard_reason": null,
      "quiet_since": null
    }
  }
}
```

## Adapter catalog

Nine adapter types. Each implements a new `EnrichmentAdapter` interface, registered in an `AdapterFactory` keyed by `type` (mirrors the existing `ParserFactory` pattern).

| Type | Discovery source | Freshness signal | Retirement trigger | 🔥 | 🫡 |
|---|---|---|---|---|---|
| `github_repo` | GH Search API | last commit, last release, stars | archived, OR (no commit 3y AND no release 3y), OR 404 for 90d | top 10% stars in category | commit ≤90d AND ≥3 releases/12mo |
| `blog` | RSS autodiscovery | RSS last-post | no post 18mo OR 404 for 90d | — | post ≤60d |
| `packagist_pkg` | Packagist API | last release, downloads, `abandoned` | abandoned, OR downloads→0, OR 404 | top 10% downloads in category | release ≤180d |
| `event` | HTTP + HTML year-regex + schema.org Event | last year found on page, HTTP status | no year ≥ (current-1) on page for 60d OR site dead 60d | — | next_date in future |
| `youtube_playlist` | YouTube Data API v3 | last video upload date | channel no upload 18mo OR 404 | ≥10k subscribers in category | upload ≤90d |
| `course` | HTTP liveness | HTTP status | 404 for 90d | — | — |
| `vendor_site` | HTTP liveness | HTTP status | 404 for 90d | — | — |
| `archive` | none | none | never | — | — |
| `canonical` | HTTP liveness | HTTP status | 404 for 90d | — | — |

```php
interface EnrichmentAdapter {
    public function enrich(Entry $entry, array $currentState): EnrichmentResult;
}
```

## Retirement thresholds (canonical list)

- **Graveyard (auto)**: archived on GitHub OR packagist `abandoned` flag → immediate
- **Graveyard (auto, slow)**: activity below threshold for 18 months, with a further 6-month grace period before moving (prevents churning seasonally inactive but still-valid tools)
- **Hard delete**: URL returns non-2xx for 90+ consecutive days
- **Pin override**: `pinned: true` silently suppresses any retirement trigger
- **Hard delete (human)**: remove the YAML line; permanent

Graveyard entries stay in sidecar state and can be resurrected if signals reverse (project un-archives, blog resumes posting).

## Pipelines (GitHub Actions)

### 1. `enrich.yml` — weekly, Mondays 02:00 UTC

```
For each entry in data/**/*.yml:
  adapter = AdapterFactory.create(entry.type)
  fresh = adapter.enrich(entry, state[entry.url])
  state[entry.url] = fresh
Commit state/enrichment.json on change
Trigger regenerate.yml via workflow_dispatch
```

Budget: ~400 entries × 2 API calls ≈ 800 requests. Well under the 5000/hour authenticated GitHub Search limit. Parallelized with 20 concurrent workers for RSS/HTTP calls.

### 2. `discover.yml` — weekly, Mondays 06:00 UTC

```
Sources (in order):
  - GH Search: topic:magento-2, topic:magento2, text "magento 2"
  - Packagist API: type=magento2-module|theme|language|component
  - Known orgs: run-as-root, elgentos, yireo, opengento, mage-os, hyva-themes, magepal

Quality gates:
  - ≥10 stars AND star velocity >2/month
  - Has a tagged release
  - Last commit ≤18 months
  - Not archived, not a fork, has declared license
  - URL not in data/**/*.yml (dedupe)
  - URL not in state/candidates.log.json rejections (dedupe rejections)

Category guess:
  composer.json type → bucket first
  README keyword match → refine subcategory
  Low-confidence → subcategory: "_triage"

Upsert weekly candidates issue (checkbox per candidate).
```

Dependency-graph mining (transitive composer deps of top entries) dropped as YAGNI; revisit if discovery recall proves poor in practice.

### 3. `accept-candidate.yml` — on issue edit

```
When the candidates issue is edited AND a box is newly checked:
  Parse the newly-checked line
  Generate full YAML entry block with known metadata
  Open PR into data/extensions/<guessed-subcategory>.yml
    (or data/extensions/_triage.yml if low confidence)
  Link back to the candidates issue
Unchecked → no-op (logged as rejection to candidates.log.json)
```

### 4. `regenerate.yml` — on push (data files) or workflow_dispatch

```
Read data/**/*.yml → entries
Load state/enrichment.json
Render README.md:
  - Preserve content/main.md top matter (intro, Ukraine banner)
  - Auto-generate TOC from categories present
  - For each category: render entries with 🔥/🫡 badges inline
  - Route graveyard_candidate && !pinned entries to <details> appendix, sorted by retirement date
Render events.ical and events.json (from data/events/**)
Commit regenerated artifacts if changed (message: "chore: regenerate [skip ci]")
```

Infinite-loop guard: all bot commits include `[skip ci]`; `regenerate.yml` filters pushes from the bot actor.

## README structure post-migration

```
# Awesome Magento 2 [badges]

<div align="center">[Ukraine banner]</div>

> A curated list of awesome Magento 2 Extensions & Resources.

[Auto-generated TOC from data/ categories]

---

## What is Magento?
[from content/main.md]

## Events: Meet the community
[rendered from data/events/mage-events.yml]

### Meet Magento
[rendered from data/events/meet-magento.yml]

## Front-ends
[rendered from data/frontends.yml, with 🔥/🫡 badges]

## Tools
## Open Source Extensions
  ### Development Utilities, Deployment, Localization, Search, CMS,
  ### Marketing, Adminhtml, Security, Payment, Infrastructure, Proprietary, PWA
## Blogs
  ### Personal, Company, Other
## Learning
## Platforms
## Official Resources
## Trustworthy Extension Developers
## Other Awesome Lists

---

## License

---

<details>
<summary>🪦 Graveyard — projects no longer recommended</summary>

Sorted by retirement date, most recent first.

- [Name](url) — Description _(Retired 2026-02-14: archived on GitHub)_
</details>
```

Entries carry badges inline:

```markdown
- [n98-magerun2](https://github.com/netz98/n98-magerun2) 🔥 🫡 - The CLI Swiss Army Knife for Magento 2.
```

## Migration path (sketch — detail belongs in the implementation plan)

Incremental, strangler pattern. The regenerator gains a "legacy passthrough" mode during migration: categories not yet in `data/` are preserved byte-for-byte from the current README; categories present in `data/` are rendered from YAML.

Proposed order:

1. Frontends (port `content/frontend.csv` → `data/frontends.yml`)
2. Tools
3. Other Awesome Lists, Platforms, Official Resources
4. Blogs (enables early RSS validation)
5. Open Source Extensions — 12 subcategories
6. Localization
7. Learning
8. Events + Meet Magento (pairs with iCal export build)
9. Masters, Trustworthy Developers

At each step: regenerate, diff against previous README, verify only intended changes. The README is always ship-able mid-migration.

## Build artifacts

Per regeneration run, `generate.php` emits:

- `README.md` — the list
- `events.ical` — iCal feed of upcoming events (per #105)
- `events.json` — JSON feed of all events (per #105)

External consumers (Google Calendar subscription, third-party websites) can reference these directly from the raw GitHub URL.

## Contributor experience

Post-migration, contributing an entry means editing one YAML file — usually a flat list of 10-30 entries in a single subcategory file. The bot handles enrichment; contributors supply only the four required fields. `CONTRIBUTING.md` is updated to reflect the new flow (closes #103).

For maintainers, the weekly candidates issue is the primary inbox: check a box → auto-PR gets opened → review and merge.

## Open questions for the implementation plan

- GitHub PAT scope and storage (likely fine-grained PAT with `contents:write`, `issues:write`, `pull-requests:write` on the repo only)
- Bot commit identity (`github-actions[bot]` vs. a dedicated bot user)
- Exact wording of the candidates issue template
- YAML validation — JSON Schema to catch shape errors in PRs before merge
- Packagist-only entries: do we auto-dual-track if the package has both a Packagist listing and a GitHub mirror?

These are tactical and belong in the implementation plan.
