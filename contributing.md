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

🔥 marks the top 10% of a category by stars (github_repo only; minimum 5 entries per category to enable). 🫡 marks actively maintained projects (commit in last 90 days + release in last year).
