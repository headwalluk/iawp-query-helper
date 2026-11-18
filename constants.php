<?php
/**
 * Plugin Constants
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Product name, shown untranslated in admin text.
 *
 * @since 0.1.0
 */
const PLUGIN_NAME = 'IAWP Query Helper';

/**
 * Options. Internal state only; the plugin has no settings in v1.
 *
 * @since 0.1.0
 */
const OPTION_PREFIX  = 'iawpqh_';
const OPTION_VERSION = 'iawpqh_version';

/**
 * Public surface: REST namespace, ability namespace and ability category.
 *
 * IAWP itself registers REST routes under `iawp`, so these stay clear of it.
 *
 * @since 0.1.0
 */
const REST_NAMESPACE    = 'iawp-query-helper/v1';
const ABILITY_NAMESPACE = 'iawp-query-helper';
const ABILITY_CATEGORY  = 'iawp-query-helper';

/**
 * IAWP's resource type for a request that returned 404 Not Found.
 *
 * @since 1.0.2
 */
const RESOURCE_TYPE_NOT_FOUND = '404';

/**
 * Query-string keys WordPress's REST transport uses itself, besides the `_`-prefixed ones.
 * `rest_route` carries the route on sites without pretty permalinks.
 *
 * @since 1.0.1
 */
const REST_TRANSPORT_PARAMS = array( 'rest_route' );

/**
 * Whether the abilities are offered to the WordPress MCP Adapter. Off until MCP support is finished.
 *
 * @since 1.0.0
 */
const IS_MCP_ENABLED = false;

/**
 * GitHub updater.
 *
 * @since 0.1.0
 */
const UPDATER_GITHUB_REPO    = 'headwalluk/iawp-query-helper';
const FILTER_UPDATER_ENABLED = 'iawpqh_updater_enabled';

/**
 * Filters that let a host tighten the safety rails.
 *
 * @since 0.1.0
 */
const FILTER_STATEMENT_TIMEOUT = 'iawpqh_statement_timeout';
const FILTER_CONCURRENCY_SLOTS = 'iawpqh_concurrency_slots';
const FILTER_MAX_RANGE_DAYS    = 'iawpqh_max_range_days';

/**
 * Independent Analytics: table suffix, options, capabilities and the schema we've tested.
 *
 * Tables are `{$wpdb->prefix}independent_analytics_<name>`.
 *
 * @since 0.1.0
 */
const IAWP_TABLE_PREFIX           = 'independent_analytics_';
const IAWP_TABLE_VIEWS            = 'views';
const IAWP_TABLE_SESSIONS         = 'sessions';
const IAWP_TABLE_RESOURCES        = 'resources';
const IAWP_TABLE_REFERRERS        = 'referrers';
const IAWP_TABLE_REFERRER_TYPES   = 'referrer_types';
const IAWP_TABLE_COUNTRIES        = 'countries';
const IAWP_TABLE_CITIES           = 'cities';
const IAWP_TABLE_DEVICE_TYPES     = 'device_types';
const IAWP_TABLE_DEVICE_OSS       = 'device_oss';
const IAWP_TABLE_DEVICE_BROWSERS  = 'device_browsers';
const IAWP_TABLE_CAMPAIGNS        = 'campaigns';
const IAWP_TABLE_LANDING_PAGES    = 'landing_pages';
const IAWP_TABLE_UTM_SOURCES      = 'utm_sources';
const IAWP_TABLE_UTM_MEDIUMS      = 'utm_mediums';
const IAWP_TABLE_UTM_CAMPAIGNS    = 'utm_campaigns';
const IAWP_TABLE_ORDERS           = 'orders';
const IAWP_TABLE_FORMS            = 'forms';
const IAWP_TABLE_FORM_SUBMISSIONS = 'form_submissions';
const IAWP_OPTION_DB_VERSION      = 'iawp_db_version';
const IAWP_OPTION_IS_MIGRATING    = 'iawp_is_migrating';
const IAWP_OPTION_WEEK_START      = 'iawp_dow';
const IAWP_CAP_READ_ONLY          = 'iawp_read_only_access';
const IAWP_CAP_FULL               = 'iawp_full_access';
const IAWP_HIGHEST_TESTED_SCHEMA  = 53;
const IAWP_PRO_CHECK_FUNCTION     = 'IAWPSCOPED\iawp_is_pro';
const IAWP_API_PROBE_FUNCTION     = 'iawp_analytics';

/**
 * Safety rails. See docs/how-it-works.md.
 *
 * @since 0.1.0
 */
const DEFAULT_STATEMENT_TIMEOUT_SECONDS = 20;
const MIN_STATEMENT_TIMEOUT_SECONDS     = 1;
const MAX_STATEMENT_TIMEOUT_SECONDS     = 120;
const DEFAULT_CONCURRENCY_SLOTS         = 2;
const MIN_CONCURRENCY_SLOTS             = 1;
const MAX_CONCURRENCY_SLOTS             = 10;
const DEFAULT_MAX_RANGE_DAYS            = 366;
const MIN_MAX_RANGE_DAYS                = 1;
const DEFAULT_PER_PAGE                  = 100;
const MAX_PER_PAGE                      = 500;
const MAX_GROUPED_ROW_OFFSET            = 10000;
const RAW_MAX_RANGE_DAYS                = 31;
const BUSY_RETRY_AFTER_SECONDS          = 10;
const LOCK_NAME_PREFIX                  = 'iawpqh_slot_';

/**
 * Date periods, matching the presets on the IAWP dashboard.
 *
 * @since 0.1.0
 */
const PERIOD_TODAY          = 'today';
const PERIOD_YESTERDAY      = 'yesterday';
const PERIOD_LAST_7_DAYS    = 'last_7_days';
const PERIOD_LAST_30_DAYS   = 'last_30_days';
const PERIOD_LAST_60_DAYS   = 'last_60_days';
const PERIOD_LAST_90_DAYS   = 'last_90_days';
const PERIOD_THIS_WEEK      = 'this_week';
const PERIOD_LAST_WEEK      = 'last_week';
const PERIOD_THIS_MONTH     = 'this_month';
const PERIOD_LAST_MONTH     = 'last_month';
const PERIOD_LAST_3_MONTHS  = 'last_3_months';
const PERIOD_LAST_6_MONTHS  = 'last_6_months';
const PERIOD_LAST_12_MONTHS = 'last_12_months';
const PERIOD_YEAR_TO_DATE   = 'year_to_date';
const PERIOD_LAST_YEAR      = 'last_year';
const DEFAULT_PERIOD        = PERIOD_LAST_30_DAYS;

/**
 * Comparison modes.
 *
 * @since 0.1.0
 */
const COMPARE_NONE            = 'none';
const COMPARE_PREVIOUS_PERIOD = 'previous_period';
const COMPARE_PREVIOUS_YEAR   = 'previous_year';

/**
 * Time-series intervals.
 *
 * @since 0.1.0
 */
const INTERVAL_DAILY   = 'daily';
const INTERVAL_WEEKLY  = 'weekly';
const INTERVAL_MONTHLY = 'monthly';

/**
 * Sort orders and the metrics a grouped query may sort by.
 *
 * @since 0.1.0
 */
const ORDER_ASC        = 'asc';
const ORDER_DESC       = 'desc';
const METRIC_VIEWS     = 'views';
const METRIC_VISITORS  = 'visitors';
const METRIC_SESSIONS  = 'sessions';
const DATE_FORMAT      = 'Y-m-d';
const DATETIME_FORMAT  = 'Y-m-d H:i:s';
const DATE_PATTERN     = '^\d{4}-\d{2}-\d{2}$';
const CURSOR_PATTERN   = '^\d{14}-\d{1,20}$';
const SECONDS_PER_HOUR = 3600;

/**
 * Decimal places MariaDB and MySQL keep when dividing integers (default `div_precision_increment`).
 *
 * @since 0.5.1
 */
const SQL_DIVISION_SCALE = 4;

/**
 * Error codes returned by the executor.
 *
 * @since 0.1.0
 */
const ERROR_IAWP_MISSING  = 'iawpqh_iawp_missing';
const ERROR_MIGRATING     = 'iawpqh_migrating';
const ERROR_REQUIRES_PRO  = 'iawpqh_requires_pro';
const ERROR_UNKNOWN_QUERY = 'iawpqh_unknown_query';
const ERROR_UNKNOWN_PAGE  = 'iawpqh_unknown_page';
const ERROR_INVALID_PARAM = 'iawpqh_invalid_param';
const ERROR_RANGE_TOO_BIG = 'iawpqh_range_too_long';
const ERROR_BUSY          = 'iawpqh_busy';
const ERROR_TIMEOUT       = 'iawpqh_timeout';
const ERROR_DATABASE      = 'iawpqh_database_error';
const ERROR_FORBIDDEN     = 'iawpqh_forbidden';

/**
 * Fragments of the server error text for a statement that hit its time limit.
 *
 * MariaDB reports error 1969, MySQL error 3024.
 *
 * @since 0.1.0
 */
const TIMEOUT_ERROR_MARKERS = array( 'max_statement_time', 'maximum statement execution time' );
