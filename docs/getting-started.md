# Getting started

## Requirements

- WordPress 6.9 or later (the Abilities API is in core from 6.9)
- PHP 8.2 or later
- Independent Analytics, free or Pro, already collecting data

## Install

1. Download `iawp-query-helper.zip` from the [latest release](https://github.com/headwalluk/iawp-query-helper/releases/latest)
2. WordPress admin, Plugins, Add New, Upload Plugin, choose the zip, Install Now, Activate

Future updates arrive through the bundled GitHub updater, like any other plugin update. Hosts who manage updates another way can switch it off; see [hooks and filters](developers/hooks-and-filters.md).

## Give the agent its own account

Create a dedicated user for the agent, in a role that has nothing but "View all analytics" in Independent Analytics, and give it an Application Password. Don't use an administrator's: an Application Password carries all of its user's permissions, across the whole REST API. [Connecting an agent](connecting-an-agent.md#authentication) has the steps.

## First call

```bash
curl -u 'analytics-agent:xxxx xxxx xxxx xxxx xxxx xxxx' \
  'https://example.com/wp-json/iawp-query-helper/v1/site-summary?period=year_to_date&compare=previous_year'
```

That's this year so far against the same dates last year. An agent should start with `catalogue`, which lists what the site offers. Every query, its parameters and the response format are in [connecting an agent](connecting-an-agent.md).

## What an agent can ask

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
- `raw-views`: individual page views, up to 31 days at a time, paged with a cursor. No visitor or session identifiers

With Independent Analytics Pro installed and licensed, eight more appear:

- `ecommerce-summary`: orders, gross and net sales, refunds, conversion rate, earnings per visitor and average order value
- `revenue-by-landing-page`, `revenue-by-referrer` and `revenue-by-campaign`: which pages, sources and campaigns bring in sales
- `form-submissions`: submissions and conversion rate for each tracked form, with site-wide totals
- `form-submissions-by-page`, `form-submissions-by-referrer` and `form-submissions-by-campaign`: which pages, sources and campaigns bring in enquiries
