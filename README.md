# IAWP Query Helper

[![PHP](https://img.shields.io/badge/PHP-8.2+-purple.svg)](https://www.php.net/)
[![WordPress](https://img.shields.io/badge/WordPress-6.9+-21759B.svg)](https://wordpress.org/)
[![License](https://img.shields.io/badge/license-GPL--2.0+-green.svg)](LICENSE)

Read-only REST routes and WordPress Abilities for querying [Independent Analytics](https://independentwp.com/) visitor and view metrics. Built so an AI agent, connected with an Application Password or through MCP, can ask "which posts did best last month?" and get the same numbers the Independent Analytics dashboard shows.

This is an independent plugin. It isn't made or supported by the Independent Analytics team.

## What it does

- A small library of named queries, each exposed twice: as a REST route under `/wp-json/iawp-query-helper/v1/` and as an ability under `iawp-query-helper/`
- Abilities appear in the core Abilities API, so the WordPress MCP Adapter picks them up as MCP tools without further setup
- Figures match the Independent Analytics dashboard for the same date range
- Date presets match the dashboard's, plus year-on-year and previous-period comparison
- Every query runs inside safety rails: a 20 second statement timeout, a limit of two queries at once per site, a maximum range of 366 days and capped page sizes. A busy analytics agent shouldn't be able to slow the site down for visitors
- Read-only. It never writes to Independent Analytics' tables

## Queries in this release

- `catalogue`: what this site offers (timezone, currency, first day with data, limits, the available queries). An agent's first call
- `site-summary`: views, visitors, sessions, bounce rate, average session length and views per session
- `traffic-over-time`: the same metrics per day, week or month
- `top-posts`: posts, pages and products ranked by views, visitors or sessions, with absolute URLs for matching against Google Search Console
- `top-pages`: every kind of page (archives, the home page, search results and 404s too), with entrances, exits, bounce rate and time on page
- `landing-pages`: where visits start
- `page-stats`: one page in detail, by post ID or URL, with its own daily, weekly or monthly series
- `site-searches` and `not-found`: what visitors searched for, and which URLs they expected to exist. Good sources of content ideas
- `referrer-types`: Search, Social, AI, Direct, Referrer and Ad
- `referrers`: individual referring sites, filterable by type
- `countries` and `cities`
- `devices`: device type, operating system or browser
- `campaigns`: UTM-tagged traffic, by whole campaign or by one UTM parameter

Planned next: WooCommerce revenue and form conversions with Independent Analytics Pro, and a paged raw export.

## Requirements

- WordPress 6.9 or later (the Abilities API is in core from 6.9)
- PHP 8.2 or later
- Independent Analytics, free or Pro

## Install

1. Download `iawp-query-helper.zip` from the [latest release](https://github.com/headwalluk/iawp-query-helper/releases/latest)
2. WordPress admin, Plugins, Add New, Upload Plugin, choose the zip, Install Now, Activate

Future updates arrive through the bundled GitHub updater.

## Quick start

Create an Application Password for a user who can view all Independent Analytics data (an administrator, or a role given "View all analytics" in Independent Analytics), then:

```bash
curl -u 'username:xxxx xxxx xxxx xxxx xxxx xxxx' \
  'https://example.com/wp-json/iawp-query-helper/v1/site-summary?period=year_to_date&compare=previous_year'
```

See [docs/connecting-an-agent.md](docs/connecting-an-agent.md) for every query, its parameters and the response format.

## Documentation

- [How it works](docs/how-it-works.md): metric definitions, safety rails, permissions
- [Connecting an agent](docs/connecting-an-agent.md): REST, Abilities and MCP, with parameters and examples
- [Hooks and filters](docs/developers/hooks-and-filters.md)

## Licence

GPL v2 or later. See [LICENSE](LICENSE).
