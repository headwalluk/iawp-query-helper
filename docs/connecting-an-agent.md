# Connecting an agent

Each named query is available three ways. They run the same code and return the same response.

- **REST:** `GET /wp-json/iawp-query-helper/v1/<query>` with parameters in the query string
- **Abilities API:** `GET /wp-json/wp-abilities/v1/abilities/iawp-query-helper/<query>/run` with parameters as `input[name]=value`
- **MCP:** through the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter), which lists the abilities as tools. Each tool's description and input schema tell the agent what it does and what it accepts

## Authentication

Create an Application Password (Users, Profile, Application Passwords) for a user who can view all Independent Analytics data, and send it with HTTP Basic authentication over HTTPS:

```bash
curl -u 'username:xxxx xxxx xxxx xxxx xxxx xxxx' \
  'https://example.com/wp-json/iawp-query-helper/v1/top-posts?period=last_month&per_page=10'
```

## Parameters shared by every query

- `period`: one of `today`, `yesterday`, `last_7_days`, `last_30_days`, `last_60_days`, `last_90_days`, `this_week`, `last_week`, `this_month`, `last_month`, `last_3_months`, `last_6_months`, `last_12_months`, `year_to_date`, `last_year`. Defaults to `last_30_days`
- `start`, `end`: `Y-m-d` dates in the site's timezone, both included. Use instead of `period`. `end` defaults to today
- `compare`: `none` (default), `previous_year` or `previous_period`

Paged queries also take `page` (from 1) and `per_page` (1 to 500, default 100).

Unknown parameters are refused rather than ignored, so a typo shows up as an error.

## Queries

### `site-summary`

Site-wide totals. One row: `views`, `visitors`, `sessions`, `bounces`, `bounce_rate_percent`, `average_session_seconds`, `views_per_session`.

```bash
curl -u "$CREDENTIALS" 'https://example.com/wp-json/iawp-query-helper/v1/site-summary?period=year_to_date&compare=previous_year'
```

### `top-posts`

Posts, pages, products and other singular content, ranked. Paged.

- `post_type`: only this post type, such as `post` or `product`. Omit for all
- `category`: only posts in this category, by term ID
- `orderby`: `views` (default), `visitors` or `sessions`

Each row: `post_id`, `title`, `post_type`, `url`, `path`, `views`, `visitors`, `sessions`. `url` is absolute, for matching against Google Search Console page reports. `path` includes any query string, which matters for drafts and other posts without a pretty permalink.

### `traffic-over-time`

A series of buckets: `period_start` (`Y-m-d`), `views`, `visitors`, `sessions`. Every bucket in the range is present, with zeros where there was no traffic.

- `interval`: `daily` (default), `weekly` or `monthly`

With `compare`, `previous_rows` holds the same series for the comparison range.

## Response format

```json
{
  "meta": {
    "query": "top-posts",
    "range": { "start": "2026-10-01", "end": "2026-10-31", "days": 31, "timezone": "Europe/London",
               "start_utc": "2026-09-30 23:00:00", "end_utc": "2026-10-31 23:59:59" },
    "compare": null,
    "generated_at": "2026-11-18T12:00:00+00:00",
    "iawp_version": "2.16.3",
    "iawp_db_version": 53,
    "is_pro": false,
    "schema_untested": false,
    "pagination": { "page": 1, "per_page": 10, "has_more": true, "next_page": 2 }
  },
  "rows": [ ]
}
```

When comparing, `meta.compare` describes the comparison range. Rows from `site-summary` and `top-posts` then carry `previous` (the same metrics for the comparison range) and `change_percent` (percentage change, one decimal place, `null` where the previous value was zero). For rates such as `bounce_rate_percent`, `change_percent` is the relative change, not the difference in percentage points.

## Errors

Errors use the usual WordPress REST format, `{ "code": "...", "message": "...", "data": { "status": 400 } }`. The message is written to be shown to, or acted on by, the agent.

- `iawpqh_invalid_param` (400): a parameter is wrong; the message says which
- `iawpqh_range_too_long` (400): more than 366 days in one range. Split it
- `iawpqh_forbidden` (401 or 403): no credentials, or the user can't view all analytics
- `iawpqh_requires_pro` (403): the query needs Independent Analytics Pro
- `iawpqh_busy` (429): two queries are already running. Wait for `Retry-After` seconds
- `iawpqh_timeout` (503): the query ran past the time limit. Narrow the range or add a filter
- `iawpqh_migrating` (503): Independent Analytics is updating its database. Retry later
- `iawpqh_iawp_missing` (503): Independent Analytics isn't active
- `iawpqh_database_error` (500): logged on the server

## Tips for agent builders

- Ask for the range you need. "This year compared with last" is one `site-summary` call with `period=year_to_date&compare=previous_year`
- Don't add up `visitors` across rows or days. Ask `site-summary` for the range instead
- Prefer filters (`post_type`, `category`) over fetching every page and filtering yourself
