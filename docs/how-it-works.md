# How it works

IAWP Query Helper reads the tables Independent Analytics keeps in your WordPress database and answers a fixed set of named queries. It never writes to those tables, never changes their structure, and never runs SQL supplied by the caller. An agent picks a query and fills in parameters from a fixed list; that's all it can do.

## Matching the dashboard

Every metric is calculated the way the Independent Analytics dashboard calculates it, so an operator comparing the two sees the same numbers. The release process includes a harness that runs each query against Independent Analytics' own dashboard code over a set of date ranges and fails on any difference.

The definitions, for a date range:

- **Sessions** are sessions that started in the range and have at least one view in the range
- **Views** are views in the range that belong to those sessions
- **Visitors** are the distinct visitors behind those sessions. Visitors are unique per row, so they can't be added up across rows or days. Use `site-summary` for range totals
- **Bounces** are sessions with a single view. **Bounce rate** is bounces as a percentage of sessions, rounded to a whole number as on the dashboard
- **Average session length** is the mean time from a session's first view to its last, in whole seconds
- **Views per session** is the total views of those sessions (including any outside the range) divided by sessions, to two decimal places, as on the dashboard

Breakdowns (referrers, referrer types, countries, cities, devices, campaigns) count the same way, grouped by the session's referrer, location, device or campaign, and match the dashboard's tables:

- Sessions with no value for the attribute (no known location, for example) are left out, as on the dashboard
- **Bounce rate** and **views per session** are to two decimal places, and views per session counts in-range views only
- **Average session length** is truncated to whole seconds, and is `null` when no session in the group has a measurable length (single-view sessions have none)

Page-level queries (`top-pages`, `landing-pages`, `page-stats`, `site-searches`, `not-found`) count as the dashboard's Pages table does:

- A page's **visitors** and **sessions** are those that viewed it at least once
- **Entrances** are sessions that started on the page. **Exits** are sessions that ended on it (a single-view session both starts and ends there). **Exit percent** is exits as a percentage of the page's views
- **Bounces** are single-view sessions that included the page; bounce rate is bounces as a percentage of the page's sessions
- **Average view seconds** is the mean time from a view of the page to the next view in the same session, truncated to whole seconds. A session's last view has no next view, so it isn't counted
- `page-stats` series count the page's own views, and the visitors and sessions that viewed it, bucketed by session start like every other series

Daily, weekly and monthly series are bucketed by the day each session started, using the site's current UTC offset, exactly as the dashboard chart does. Weeks start on the day set in Independent Analytics. One consequence, shared with the dashboard: across a clock change, an hour of traffic can land in the neighbouring day.

The Independent Analytics developer API (`iawp_analytics()` and friends) counts slightly differently, filtering on view time only. That's why this plugin reads the tables rather than calling it: its numbers can drift from the dashboard at the edges of a range.

## Dates

All dates are in the site's timezone (Settings, General). A range is whole days, first and last day included. Presets match the dashboard's: `last_30_days` is today and the 29 days before it, `last_12_months` is the twelve full calendar months before this one, and `year_to_date` is 1 January to today (the dashboard calls it "This Year").

Comparisons:

- `previous_year` uses the same dates one year earlier. 29 February becomes 28 February
- `previous_period` uses the same number of days immediately before the range

## Safety rails

Analytics tables get large, and many WordPress sites share a modest database server with everything else the site does. The plugin assumes an agent will eventually ask for something expensive, and limits the damage:

- **Statement timeout.** Every statement runs with a 20 second limit, enforced by the database server (`max_statement_time` on MariaDB, `MAX_EXECUTION_TIME` on MySQL 5.7.8 and later). A query that hits it is stopped and the caller is told to narrow the range
- **Two at once.** Only two queries run at the same time per site, across every PHP worker. A third gets HTTP 429 with a `Retry-After` header
- **366 days at most** in any one range. A comparison range gets its own allowance, so a full year against the previous year works
- **Page sizes** are capped at 500 rows, and paging stops after 10,000 rows
- **No total counts.** Pages report whether there is more, rather than counting every row

Hosts can tighten these with filters; see [hooks and filters](developers/hooks-and-filters.md).

## Who can run queries

The same people who can see all analytics in Independent Analytics: administrators, and any role given "View all analytics" or "View all analytics and edit settings" in its settings. Roles limited to "View analytics for authored content" are refused for now.

Agents connect with an Application Password belonging to one of those users. See [connecting an agent](connecting-an-agent.md).

## Independent Analytics Pro

Queries that need Pro features (WooCommerce revenue, form submissions) will only appear when Pro is installed and licensed. This plugin isn't a way around buying Pro.

## Compatibility

The plugin records which Independent Analytics database version it has been tested with. When Independent Analytics moves past it, queries keep running, responses carry `"schema_untested": true`, and administrators see a notice suggesting a check for an update. While Independent Analytics is migrating its database, queries return HTTP 503 and ask the caller to retry.
