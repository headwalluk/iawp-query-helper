# Changelog

All notable changes to IAWP Query Helper will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

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
