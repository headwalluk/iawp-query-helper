# Changelog

All notable changes to IAWP Query Helper will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

### Fixed

- The GitHub updater now reads each release's `Requires at least` and `Requires PHP`, and refuses a background auto-update to a release that needs a newer WordPress or PHP than the site runs. Returning `false` from `iawpqh_updater_enabled` now also withdraws an update offer already stored. Requests to GitHub send a plain `WordPress` user agent instead of one naming the site's URL. Present since 0.1.0.

### Documentation

- `docs/how-it-works.md` lists the plugin's only outbound requests, the updater's, and what GitHub sees of them.

### Changed

- The release workflow pins its actions to commit SHAs and no longer uploads build artifacts.

## [1.0.2] - 2026-11-18

### Changed

- 404 URLs are sent to agents without their query string or fragment, in `not-found`, the other page-level queries, the revenue and form breakdowns by page, and `raw-views`. Independent Analytics records the full requested URL, which can carry a password-reset or invitation token. Two rows can now share a path where Independent Analytics recorded them with different query strings. Other pages keep their query strings.

### Documentation

- `docs/how-it-works.md` has a new section, "What agents receive", on the visitor-supplied text agents get: search terms exactly as typed, 404 paths without query strings, and individual views without visitor or session identifiers.

## [1.0.1] - 2026-11-18

### Fixed

- The REST routes ignored parameters they didn't recognise, so a misspelt `period` ran the default 30 days instead of failing. They are now refused with `iawpqh_invalid_param`, as the Abilities API already did. WordPress's own `_fields`, `_envelope` and similar still work.
- On a very long result, the last page allowed by the 10,000-row paging limit advertised a `next_page` that was then refused. That page now has `next_page: null`, and the new `meta.pagination.is_capped` is `true` to say there were more rows than can be paged through.

### Documentation

- Agents should have their own user, in a role with nothing but "View all analytics". An Application Password carries every permission of its user across the whole REST API, so an administrator's is far more than an analytics agent needs. The steps are in `docs/connecting-an-agent.md`.
- `docs/how-it-works.md` explains that a daily series for a summer range, viewed in winter, can total slightly less than `site-summary` for the same range, as on the dashboard.

## [1.0.0] - 2026-11-18

First stable release. The REST routes, ability names, parameters, response fields, error codes and filters documented in `docs/` are now the public interface, and won't change in a way that breaks callers within 1.x.

### Changed

- The abilities are no longer offered to the WordPress MCP Adapter. MCP support, with a dedicated server listing each query as its own tool, is planned for a later release. The REST routes and the Abilities API are unaffected.

### Documentation

- `docs/connecting-an-agent.md` covers cursor paging, the per-query range limits and the `catalogue` fields added in 0.5.0, and lists the errors an agent actually receives, including WordPress's own 404 for a query this site doesn't offer.
- Checked against a development site and copies of two production sites, with up to 1.5 million views and four years of data, including WooCommerce orders with refunds, Contact Form 7 submissions and year-on-year comparisons. Every figure matched the Independent Analytics dashboard.

## [0.5.1] - 2026-11-18

### Fixed

- Rates in the breakdowns, page-level queries and the `revenue-by-*` and `form-submissions-by-*` queries could differ from the dashboard by 0.01. Independent Analytics divides in SQL, where the result is held to four decimal places, then rounds again for display; this plugin now does the same arithmetic. Found on a site with 1.5 million views.
- Rows with equal values are now ordered as on the dashboard (by title, name or label), so the rows at a page boundary are the same ones the dashboard shows.

## [0.5.0] - 2026-11-18

### Added

- Form queries, available only with a licensed copy of Independent Analytics Pro: `form-submissions` (per form, with site-wide totals), `form-submissions-by-page`, `form-submissions-by-referrer` and `form-submissions-by-campaign`. Figures match the dashboard's form statistics and table columns.
- `raw-views`: individual page views, up to 31 days per range, paged with a cursor. No visitor or session identifiers.

## [0.4.0] - 2026-11-18

### Added

- WooCommerce queries, available only with a licensed copy of Independent Analytics Pro: `ecommerce-summary`, `revenue-by-landing-page`, `revenue-by-referrer` and `revenue-by-campaign`. Figures match the dashboard's ecommerce statistics and table columns. Without Pro they are not registered at all.

## [0.3.0] - 2026-11-18

### Added

- `top-pages`: every kind of page (posts, archives, the home page, search results, 404s), with entrances, exits, exit percent, bounces, bounce rate, views per session and average time on page. Filter by `resource_type`, `post_type` or exact `url`. Matches the dashboard's Pages table.
- `landing-pages`: pages ranked by entrances.
- `page-stats`: one page by `post_id` or `url`, with its own daily, weekly or monthly series and comparison.
- `site-searches` and `not-found`: search terms visitors used, and requested paths that returned 404.

## [0.2.0] - 2026-11-18

First release. Development versions before it went only to a test site.

### Added

- `catalogue` query: site timezone, week start, currency, first day with data, limits and the list of available queries.
- Breakdown queries, each matching the corresponding Independent Analytics dashboard table: `referrer-types`, `referrers` (filter by `referrer_type`), `countries`, `cities` (filter by `country_code`), `devices` (`dimension`: device type, operating system or browser) and `campaigns` (`dimension`: whole campaign or one UTM parameter).
- Three named queries, each as a REST route under `iawp-query-helper/v1` and an ability under `iawp-query-helper/`: `site-summary`, `top-posts` and `traffic-over-time`.
- Date presets matching the Independent Analytics dashboard, explicit `start`/`end` dates, and `compare` with `previous_year` or `previous_period`.
- Safety rails on every query: a 20 second statement timeout (MariaDB and MySQL), two queries at once per site, a 366 day maximum range, capped page sizes and paging depth.
- Filters to tighten the rails: `iawpqh_statement_timeout`, `iawpqh_concurrency_slots`, `iawpqh_max_range_days`.
- Admin notices when Independent Analytics is missing, or its database version is newer than this plugin has been tested with.
- GitHub updater, switched off with `iawpqh_updater_enabled`.
