# CLAUDE.md

Repository: public
Follows the headwall-wp-plugin skill. Load it before working on this plugin; this file holds
only what is specific to IAWP Query Helper.

## Project Overview

- **Namespace:** `IAWP_Query_Helper` · **Text domain:** `iawp-query-helper` · **Prefixes:** `iawpqh_`, `IAWPQH_`
- **PHP / WordPress floor:** 8.2 / 6.9. 6.9 is the first release with the Abilities API in core
- **Depends on:** Independent Analytics (IAWP), free or Pro, detected at runtime. There is no
  `Requires Plugins:` header because either of two slugs satisfies it and Pro isn't on wordpress.org

Read-only access to IAWP's visitor and view metrics for AI agents. A library of named queries,
each registered as a REST route (`iawp-query-helper/v1/<name>`) and an ability
(`iawp-query-helper/<name>`). The MCP Adapter exposes the abilities as MCP tools.

## Request flow

1. `Rest_Controller::handle_request()` or the ability's `execute_callback` hands raw input to
   `Executor::execute()`. Permission was already checked by `check_query_permission()`
2. `Executor` checks IAWP is present and not migrating, and that Pro-only queries have Pro;
   fills defaults; validates then sanitises against the query's input schema (validate first:
   sanitising drops unknown keys, which must be refused)
3. `Date_Range_Resolver` turns `period` or `start`/`end` into a `Date_Range` and works out any
   comparison range; ranges over the filtered maximum are refused
4. `Query_Runner::with_slot()` takes a `GET_LOCK` slot and runs `Query::run()`. Every statement
   goes through `Query_Runner::get_rows()`, which adds the server's statement timeout and maps
   errors to `WP_Error`
5. `Executor::build_envelope()` adds the `meta` block

## Key files

- `includes/class-query.php`: base class; shared input schema and output schema helpers
- `includes/queries/`: one class per named query. Add a query here and in `Query_Registry`
- `includes/class-dimension-query.php`: base for breakdowns by a session attribute (referrer,
  country, device, campaign). Subclasses declare the group column, joins and labels
- `includes/class-resource-query.php`: base for page-level queries (`top-pages` and friends),
  matching the dashboard's Pages table
- `includes/class-form-breakdown-query.php`, `class-dimension-form-breakdown-query.php`: Pro-only form
  submissions by page, referrer or campaign, built the same way as the revenue queries
- `includes/queries/class-raw-views-query.php`: the only unaggregated query. Keyset cursor on
  `(viewed_at, id)`; never add visitor, session or city fields to it
- `includes/class-revenue-query.php`, `class-dimension-revenue-query.php`: Pro-only revenue
  queries. They rank groups by orders, then take traffic and labels from the matching
  breakdown or page query (composition, not inheritance). **High-impact**: money figures
- `includes/class-query-runner.php`: the safety rails. **High-impact**: every SQL statement
  against a customer's database passes through it
- `includes/class-environment.php`: IAWP detection, table names, site offset, week start
- `includes/class-date-range-resolver.php`: presets defined exactly as IAWP's
  `Relative_Date_Range` defines them

## Rules specific to this plugin

- **Metrics match the IAWP dashboard.** Every query is checked against IAWP's own dashboard
  classes by `dev-notes/testing/test-dashboard-parity.php`. A new query gets parity cases there
  before it ships. Definitions are in `docs/how-it-works.md`
- **Never call IAWP's PHP classes from shipped code.** They're scoped and internal. Read the
  tables; the schema is guarded by `IAWP_HIGHEST_TESTED_SCHEMA`. Harnesses may call internals
- **The IAWP Developer API (`iawp_analytics()` etc.) is not used for figures**: it filters on
  view time only and drifts from the dashboard. `iawp_analytics` is used only to detect IAWP
- **Every statement goes through `Query_Runner::get_rows()`.** No direct `$wpdb` reads of IAWP
  tables elsewhere, or the timeout doesn't apply
- **No subqueries in a grouped query** unless the dashboard uses the same shape; prefer two
  cheap statements to one expensive one. `EXPLAIN` new SQL against a large dataset
- **Pro queries are not bypassable.** No filter, constant or option may force
  `Environment::is_pro()` to true in shipped code. Harnesses inject it instead
- **No result caching** until there's evidence a query needs it

## Data storage

- `iawpqh_version` (option, not autoloaded): installed version
- Updater cache transients `iawp_query_helper_github_release` / `_failed`
- `uninstall.php` deletes both: the plugin keeps no user data, and never touches IAWP's tables

## Public contracts

REST namespace `iawp-query-helper/v1`, route and ability names, input parameter names and enum
values, response field names, error codes, and the four filters in
`docs/developers/hooks-and-filters.md`.

## Testing

```bash
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-dashboard-parity.php
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-dimension-parity.php
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-page-parity.php
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-ecommerce-parity.php
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-forms-and-raw.php
wp eval-file wp-content/plugins/iawp-query-helper/dev-notes/testing/test-executor-and-rails.php
```

The rails harness runs a deliberate runaway query (stopped by a 1 second timeout) and opens a
second database connection to hold the concurrency locks.

<!-- wp-translate:begin v=1.2.0 hash=d354d589bbb9f5e5d4961a3b75811a8f469511499bcabb3714f2bbfeb3c0a41f -->
## Translating this plugin (wp-translate conventions)

This plugin's `.po`/`.mo` files are generated from source by
[wp-translate](https://github.com/headwalluk/wp-translate-tool), which
machine-translates strings with DeepL. Machine translation is only as good as
the strings you give it — follow these conventions when adding or editing
user-facing text.

### 1. Disambiguate short or ambiguous strings with `_x()`

DeepL handles full sentences well but guesses badly on short, context-free
labels. Give it context with `_x()` (or `esc_html_x()`, `_ex()`):

```php
// Ambiguous out of context — DeepL may read "Sent" as "late", "Folder" as "leaflet"
__( 'Sent', 'iawp-query-helper' );

// Disambiguated — the context is passed to the translator and to DeepL
_x( 'Sent', 'email delivery status', 'iawp-query-helper' );
_x( 'Folder', 'IMAP mailbox', 'iawp-query-helper' );
_x( 'Open', 'verb; button label', 'iawp-query-helper' );
```

The context (2nd argument) is never shown to users. Use it whenever a string is a
single word, a short label, or has more than one plausible meaning.

### 2. Use placeholders, never concatenation

Build dynamic text with `printf`/`sprintf` so the whole sentence translates as a
unit, and add a `translators:` comment to explain each placeholder:

```php
/* translators: %s is the user's display name */
printf( esc_html__( 'Welcome back, %s', 'iawp-query-helper' ), $name );
```

Never split a sentence across multiple translation calls — word order differs
between languages.

### 3. Use `_n()` for anything that can be counted

Never build a count-dependent sentence by hand, and never settle for a single
form that reads correctly only for one number. Languages differ in how many
plural forms they have — English and German have two, French treats 0 as
singular, Polish and Russian have three, Japanese has one, Arabic has six — and
`_n()` is the only way to express that.

```php
// Wrong — "1 reviews", and untranslatable into languages with other forms
printf( esc_html__( '%d reviews', 'iawp-query-helper' ), $count );

// Right — wp-translate fills every form the target locale needs
printf(
    esc_html( _n( '%d review', '%d reviews', $count, 'iawp-query-helper' ) ),
    $count
);
```

Keep the placeholder in **both** forms, even when the singular reads fine
without it (`'%d review'`, not `'One review'`) — some locales use the singular
slot for other numbers too.

For a short or ambiguous countable noun, use `_nx()` — the plural equivalent of
`_x()` — so the context reaches DeepL:

```php
// "Review" alone is ambiguous: critique? opinion? inspection?
_nx( '%d review', '%d reviews', $count, 'customer feedback on a company', 'iawp-query-helper' );
```

**Locales needing more than two forms will have their extra slots left empty for
a human translator.** DeepL supplies a singular and a plural; nobody can invent
Polish's third form from those, and wp-translate deliberately leaves it blank
rather than filling it with a plausible guess. Expect to see empty
`msgstr[2]` entries in `pl_PL` — that is correct behaviour, not a failure.

### 4. Acronyms and technical tokens

wp-translate keeps common acronyms (`TLS`, `API`, `SMTP`, `URL`, `ID`, `UTC`, …)
verbatim automatically. If you introduce an unusual acronym or product name that
must not be translated, keep it as its own standalone string so it is recognised,
or ask the maintainer to add it to the tool's acronym list.

### 5. Don't translate dates — let WordPress localise them

Never add month or day-of-week names (full or abbreviated) as translatable
strings. DeepL frequently mistranslates short forms like `Mon`, `Tue`, `Jan`,
`Feb` even with context hints. WordPress already ships locale-aware names — use
`$wp_locale`:

```php
global $wp_locale;
$wp_locale->get_month( $month_number );        // "January" (1-based)
$wp_locale->get_month_abbrev( $month_name );   // "Jan"
$wp_locale->get_weekday( $weekday_number );     // "Monday" (0 = Sunday)
$wp_locale->get_weekday_abbrev( $weekday_name ); // "Mon"
```

For formatted dates, prefer `wp_date()` / `date_i18n()`, which localise month and
day names automatically.

### 6. English source dialect

Write source strings in standard English. wp-translate handles English targets
locally (no DeepL): `en`/`en_US` use the source as-is, and `en_GB`/`en_AU`/… get
American spellings converted to British automatically (`color` → `colour`).

### Running wp-translate

After changing strings, regenerate translations:

```bash
wp-translate /path/to/this-plugin              # auto-detect locales from languages/
wp-translate /path/to/this-plugin en_GB,fr_FR  # explicit locales
wp-translate /path/to/this-plugin --dry-run    # preview; no API calls, no writes
```

Requires WP-CLI (`wp`) and a DeepL API key at `~/.config/deepl.env`. The tool
regenerates the `.pot` from source, translates new/changed strings for each
locale, and compiles the `.mo` files.
<!-- wp-translate:end -->
