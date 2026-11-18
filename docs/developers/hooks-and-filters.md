# Hooks and filters

This is the **public extension surface** of IAWP Query Helper. Anything not listed here is internal and may change between releases. Functions and classes in the `IAWP_Query_Helper` namespace are private. The REST routes and ability names are documented in [connecting an agent](../connecting-an-agent.md).

## Compatibility

- A filter's name and arguments don't change within a major version. New arguments are only added at the end.
- Any change that could break an integration is listed in `CHANGELOG.md`.

## Filters

### `iawpqh_statement_timeout`

Seconds a single statement may run before the database server stops it. Default 20. Clamped to 1 to 120.

**Parameters:** `int $seconds`

**Returns:** `int`

```php
add_filter( 'iawpqh_statement_timeout', 'my_site_analytics_timeout' );

/**
 * Give analytics queries less time on this site.
 *
 * @param int $seconds Default timeout.
 * @return int
 */
function my_site_analytics_timeout( int $seconds ): int {
	return 10;
}
```

### `iawpqh_concurrency_slots`

How many queries may run at once across the site. Default 2. Clamped to 1 to 10.

**Parameters:** `int $slots`

**Returns:** `int`

### `iawpqh_max_range_days`

Longest date range, in days, any one query may cover. Default 366. Can only be lowered: values are clamped to 1 to 366. `raw-views` has its own limit of 31 days, and takes the lower of the two.

**Parameters:** `int $days`

**Returns:** `int`

### `iawpqh_updater_enabled`

Whether the bundled GitHub updater checks for new releases. Default `true`. Return `false` to manage updates another way; this affects only this plugin.

**Parameters:** `bool $is_enabled`

**Returns:** `bool`
