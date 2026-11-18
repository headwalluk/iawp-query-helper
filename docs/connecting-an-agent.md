# Connecting an agent

Each named query is available two ways. They run the same code and return the same response.

- **REST:** `GET /wp-json/iawp-query-helper/v1/<query>` with parameters in the query string. This is the main way in, and what the examples below use
- **Abilities API:** `GET /wp-json/wp-abilities/v1/abilities/iawp-query-helper/<query>/run` with parameters as `input[name]=value`. Each ability carries the same description and input and output schemas, so an agent framework that reads abilities can pick the queries up directly

MCP isn't supported yet. The queries are hidden from the [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) for now, and MCP support with its own server is planned for a later release.

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

Paged queries also take `page` (from 1) and `per_page` (1 to 500, default 100). `raw-views` is the exception: it pages with a `cursor` instead of `page`, and doesn't take `compare` (see [raw views](#raw-views)). `catalogue` takes no parameters at all.

Unknown parameters are refused rather than ignored, so a typo shows up as an error.

## Queries

### `catalogue`

Takes no parameters. One row describing the site: `site_url`, `timezone`, `utc_offset`, `week_starts_on`, `currency` (when WooCommerce is active), `first_view_date`, `is_pro`, `limits` (`max_range_days`, `max_per_page`, `max_row_offset`, `statement_timeout_seconds`, `concurrent_queries`), `periods`, and `queries`. Each entry in `queries` has `name`, `ability`, `rest_route`, `label`, `description`, `is_paged`, `paging` (`page`, `cursor` or `none`), `max_range_days` (`null` for `catalogue`), `is_pro_only` and `is_available` (whether it can run on this site). Agents should call it first.

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

### Page-level queries

`top-pages`, `landing-pages`, `site-searches` and `not-found` share one row shape. Labels: `resource_type`, `title`, `url`, `path`, `post_id`, `post_type`, `search_query`, `not_found_url` (the last two are only set on search and 404 pages). Metrics: `views`, `visitors`, `sessions`, `entrances`, `exits`, `exit_percent`, `bounces`, `bounce_rate_percent`, `views_per_session`, `average_view_seconds`. All are paged and take `orderby` (`views`, `visitors`, `sessions`, `entrances` or `exits`).

- `top-pages`: every kind of page. Filter with `resource_type` (`singular`, `home`, `post_type_archive`, `term_archive`, `author_archive`, `date_archive`, `search`, `404`, `virtual_page`), `post_type`, or an exact `url`
- `landing-pages`: pages with at least one entrance, ranked by entrances. Takes the same filters as `top-pages`
- `site-searches`: one row per search term typed into the site's own search
- `not-found`: one row per requested path that returned a 404

### `page-stats`

One page in detail. Give `post_id` or `url` (exactly one), and optionally `interval` (`daily`, `weekly`, `monthly`). Returns one row with the page-level fields above, and `series`: `period_start`, `views`, `visitors`, `sessions` for that page. With `compare`, `previous_series` holds the comparison series. A page Independent Analytics has never recorded returns `iawpqh_unknown_page` (404).

```bash
curl -u "$CREDENTIALS" -G 'https://example.com/wp-json/iawp-query-helper/v1/page-stats' \
  --data-urlencode 'url=https://example.com/my-post/' --data-urlencode 'period=last_90_days' --data-urlencode 'interval=weekly'
```

### WooCommerce (Independent Analytics Pro)

Only present when Pro is licensed; `catalogue` shows whether they're available. Money fields are in the store's base currency in major units.

- `ecommerce-summary`: one row with `currency`, `visitors`, `orders`, `gross_sales`, `refunds`, `refunded_amount`, `net_sales`, `conversion_rate_percent`, `earnings_per_visitor`, `average_order_value`
- `revenue-by-landing-page`: the page each ordering session started on (`resource_type`, `title`, `url`, `path`, `post_id`, `post_type`)
- `revenue-by-referrer`: referrer labels as in `referrers`; filter with `referrer_type`
- `revenue-by-campaign`: campaign labels as in `campaigns`; choose with `dimension`

The `revenue-by-*` rows carry `visitors`, `sessions` and the same order fields as `ecommerce-summary`. They're paged and take `orderby`: `net_sales` (default), `gross_sales` or `orders`. Only groups with orders are listed.

### Forms (Independent Analytics Pro)

Only present when Pro is licensed.

- `form-submissions`: one row per tracked form, with `form_id`, `title`, `submissions` and `conversion_rate_percent` (submissions as a percentage of site visitors). Every form is listed, including those with no submissions in the range. A `totals` object holds `visitors`, `submissions` and `conversion_rate_percent` for the whole site
- `form-submissions-by-page`: the page each form was submitted on (`resource_type`, `title`, `url`, `path`, `post_id`, `post_type`)
- `form-submissions-by-referrer`: referrer labels as in `referrers`; filter with `referrer_type`
- `form-submissions-by-campaign`: campaign labels as in `campaigns`; choose with `dimension`

The `form-submissions-by-*` rows carry `visitors`, `sessions`, `submissions` and `conversion_rate_percent` (submissions as a percentage of that row's visitors). They're paged, list only groups with submissions, most first, and take `form_id` to count one form.

### Raw views

`raw-views` returns individual page views, oldest first: `view_id`, `viewed_at` (UTC), `viewed_at_local` (site time), the page fields as in `top-pages`, `referrer`, `referrer_domain`, `referrer_type`, `country_code`, `device_type` and `is_entrance`. There are no visitor or session identifiers, so views can't be joined into visits.

Ranges are limited to 31 days and `compare` isn't accepted. Instead of `page`, it pages with a cursor: leave `cursor` out for the first page, then pass `meta.pagination.next_cursor` back unchanged until `meta.pagination.has_more` is false.

```bash
curl -u "$CREDENTIALS" 'https://example.com/wp-json/iawp-query-helper/v1/raw-views?period=last_7_days&per_page=500'
curl -u "$CREDENTIALS" 'https://example.com/wp-json/iawp-query-helper/v1/raw-views?period=last_7_days&per_page=500&cursor=20261115093012-48121'
```

For totals and rankings the grouped queries are faster and match the dashboard; use `raw-views` only when individual views matter.

### Breakdowns

These share one row shape: their own label fields, then `views`, `visitors`, `sessions`, `bounces`, `bounce_rate_percent`, `views_per_session` and `average_session_seconds`. All are paged and take `orderby` (`views`, `visitors` or `sessions`).

- `referrer-types`: `referrer_type` (Search, Social, AI, Direct, Referrer, Ad)
- `referrers`: `referrer`, `domain`, `referrer_type`. Filter with `referrer_type`
- `countries`: `country_code`, `country`, `continent`
- `cities`: `city`, `subdivision`, `country_code`, `country`. Filter with `country_code`, such as `GB`
- `devices`: `device`. Choose with `dimension`: `device_type` (default), `os` or `browser`
- `campaigns`: choose with `dimension`. `campaign` (default) returns `landing_page`, `utm_source`, `utm_medium`, `utm_campaign`, `utm_term` and `utm_content`; `utm_source`, `utm_medium`, `utm_campaign` or `landing_page` return just that field

```bash
curl -u "$CREDENTIALS" 'https://example.com/wp-json/iawp-query-helper/v1/referrers?period=last_month&referrer_type=Search'
```

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

`meta.range` is `null` for `catalogue`, which has no date range. `meta.pagination` is only present on paged queries. For `raw-views` it is `{ "per_page": 500, "has_more": true, "next_cursor": "20261115093012-48121" }` instead, with `next_cursor` `null` on the last page.

Some queries add keys beside `rows`: `previous_rows` (`traffic-over-time`), `series` and `previous_series` (`page-stats`) and `totals` (`form-submissions`).

When comparing, `meta.compare` describes the comparison range. Rows from every query except `traffic-over-time`, `catalogue` and `raw-views` (and the `totals` object of `form-submissions`) then carry `previous` (the same metrics for the comparison range) and `change_percent` (percentage change, one decimal place, `null` where the previous value was zero). For rates such as `bounce_rate_percent`, `change_percent` is the relative change, not the difference in percentage points.

## Errors

Errors use the usual WordPress REST format, `{ "code": "...", "message": "...", "data": { "status": 400 } }`. The message is written to be shown to, or acted on by, the agent.

- `iawpqh_invalid_param` (400): a parameter is wrong; the message says which
- `iawpqh_range_too_long` (400): the range is longer than the query allows: 366 days, 31 for `raw-views`, or less if the site has lowered it. `catalogue` lists each query's limit. Split the range
- `iawpqh_forbidden` (401 or 403): no credentials, or the user can't view all analytics
- `iawpqh_busy` (429): the site's limit on queries running at once (two by default) is reached. Wait for `Retry-After` seconds
- `iawpqh_timeout` (503): the query ran past the time limit. Narrow the range or add a filter
- `iawpqh_migrating` (503): Independent Analytics is updating its database. Retry later
- `iawpqh_unknown_page` (404): `page-stats` was given a page Independent Analytics has never recorded
- `rest_no_route` or `rest_ability_not_found` (404), from WordPress itself: there's no query with that name on this site. That includes the Pro-only queries on a site without a Pro licence, which aren't registered at all. `catalogue` lists what's available
- `iawpqh_iawp_missing` (503): Independent Analytics isn't active
- `iawpqh_database_error` (500): logged on the server

## Tips for agent builders

- Start with `catalogue`: it says what's available, the site's timezone and the first day with data
- Ask for the range you need. "This year compared with last" is one `site-summary` call with `period=year_to_date&compare=previous_year`
- Don't add up `visitors` across rows or days. Ask `site-summary` for the range instead
- Prefer filters (`post_type`, `category`) over fetching every page and filtering yourself
- To look up a page from a Google Search Console report, pass its URL to `page-stats` or to `top-pages` as `url`. The URL must match the one Independent Analytics recorded, including the trailing slash
