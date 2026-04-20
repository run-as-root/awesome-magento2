# Contribution Guidelines

* Use the following format: `[Package Name](Link URL) - Description`
* The link should be the name of the package or project
* Keep descriptions concise, clear and simple
* New categories, or improvements to the existing ones are also welcome

## General Quality Standard

To get on the list, PR's should follow some quality standards. They should:

* Generally useful to the community.
* Actively maintained (even if that just means acknowledging open issues when they arise).
* Stable.
* Documented.

### Extension Quality Standard

To add an awesome Magento related extension, there are some additional Quality Gates to pass:

* Make sure your Extension's README explains the following parts:
 * General Purpose of the Extension.
 * How to Install.
 * Supported Magento Versions.
 * Requirements (e.g. PHP Version).
* At least one stable release.

If you need help to pass the quality gates, add your questions to the PR too. 

## Blog Quality Standard

Make sure you pass the following Quality Gates:

* The Blog itself should contain posts from at least 3 Months ago.
* The Blog should be focused on Magento.
* There should be at least one article a month.

Thanks to all contributors, you're awesome and wouldn't be possible without you!

## Adding an entry (YAML flow)

Once a category has been migrated (see `data/`), contributing is editing one YAML file:

```yaml
- name: Your Project
  url: https://github.com/you/your-project
  description: One-line, concise.
  type: github_repo
  added: "2026-04-19"
```

Required: `name`, `url` (except `type: archive`), `description`, `type`, `added`. Valid `type` values: `github_repo`, `blog`, `packagist_pkg`, `event`, `youtube_playlist`, `course`, `vendor_site`, `archive`, `canonical`.

Run `composer validate-data` to verify your YAML. CI regenerates `README.md` on merge; do not edit it directly.

### Graveyard and badges

Entries flagged `graveyard_candidate` in `state/enrichment.json` move into a collapsed "Graveyard" block at the bottom of their section. Mark an entry `pinned: true` (with a `pin_reason`) to opt out — useful for canonical resources that won't see modern activity.

Every entry type is automatically checked for freshness and link-liveness by the weekly enrichment job (Mondays 02:00 UTC). Signals per type:

- `github_repo`: 🔥 top-10% stars per category; 🫡 commit ≤90d + release ≤365d; 🪦 archived or no activity 3+ years.
- `packagist_pkg`: 🔥 top-10% monthly downloads per category; 🫡 release ≤180d and not abandoned; 🪦 abandoned or 404.
- `blog`: 🫡 post ≤60d via RSS autodiscovery; 🪦 no post 18mo or host dead.
- `event`: 🫡 page mentions current or next year; 🪦 404.
- `youtube_playlist` (playlist or channel URLs): 🫡 upload ≤90d; 🪦 no upload 18mo or 404. Requires `YOUTUBE_API_KEY` secret.
- `vendor_site`, `course`, `canonical`: liveness-only. 🪦 after 90 consecutive days returning non-2xx.
- `archive`: no badges, no retirement.

No manual intervention is required; edits land in `data/**/*.yml`, CI does the rest.
