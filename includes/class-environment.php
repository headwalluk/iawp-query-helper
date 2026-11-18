<?php
/**
 * Independent Analytics detection: presence, Pro licence, schema version, table names.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * What we know about the installed copy of Independent Analytics.
 *
 * @since 0.1.0
 */
class Environment {

	/**
	 * Whether IAWP's tables exist, once checked this request.
	 *
	 * @since 0.1.0
	 * @var bool|null
	 */
	private ?bool $has_tables = null;

	/**
	 * Whether IAWP (free or Pro) is loaded. Costs no database query.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_iawp_loaded(): bool {
		return function_exists( IAWP_API_PROBE_FUNCTION );
	}

	/**
	 * Whether IAWP is loaded and its tables exist.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_available(): bool {
		return $this->is_iawp_loaded() && $this->has_tables();
	}

	/**
	 * Whether IAWP Pro is installed and licensed.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_pro(): bool {
		$is_pro = false;

		if ( function_exists( IAWP_PRO_CHECK_FUNCTION ) ) {
			$is_pro = (bool) call_user_func( IAWP_PRO_CHECK_FUNCTION );
		}

		return $is_pro;
	}

	/**
	 * Whether IAWP is part-way through a database migration.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_migrating(): bool {
		return (bool) filter_var( get_option( IAWP_OPTION_IS_MIGRATING, false ), FILTER_VALIDATE_BOOLEAN );
	}

	/**
	 * IAWP's schema version, or 0 when unknown.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_db_version(): int {
		return (int) get_option( IAWP_OPTION_DB_VERSION, 0 );
	}

	/**
	 * Whether IAWP's schema is newer than the highest version we have tested against.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_schema_untested(): bool {
		return $this->get_db_version() > IAWP_HIGHEST_TESTED_SCHEMA;
	}

	/**
	 * IAWP's plugin version, or an empty string.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_iawp_version(): string {
		return defined( 'IAWP_VERSION' ) ? (string) constant( 'IAWP_VERSION' ) : '';
	}

	/**
	 * Full name of an IAWP table, such as `wp_independent_analytics_views`.
	 *
	 * @since 0.1.0
	 *
	 * @param string $table_name One of the IAWP_TABLE_* constants.
	 * @return string
	 */
	public function get_table( string $table_name ): string {
		global $wpdb;

		return $wpdb->prefix . IAWP_TABLE_PREFIX . $table_name;
	}

	/**
	 * The site's current UTC offset as `+HH:MM`, built the way IAWP builds it for CONVERT_TZ().
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_site_offset(): string {
		$offset_hours  = (float) get_option( 'gmt_offset' );
		$sign          = $offset_hours < 0 ? '-' : '+';
		$total_minutes = (int) round( abs( $offset_hours ) * 60 );

		return sprintf( '%s%02d:%02d', $sign, intdiv( $total_minutes, 60 ), $total_minutes % 60 );
	}

	/**
	 * First day of the week as IAWP stores it: 0 for Sunday through 6 for Saturday.
	 *
	 * @since 0.1.0
	 *
	 * @return int
	 */
	public function get_week_start_day(): int {
		$day = (int) get_option( IAWP_OPTION_WEEK_START, 0 );

		return ( $day >= 0 && $day <= 6 ) ? $day : 0;
	}

	/**
	 * Check once per request that IAWP's views table exists.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	private function has_tables(): bool {
		global $wpdb;

		if ( is_null( $this->has_tables ) ) {
			$views_table = $this->get_table( IAWP_TABLE_VIEWS );

			// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- Checking a third-party table exists; memoised per request.
			$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $views_table ) ) );

			$this->has_tables = ( $found === $views_table );
		}

		return $this->has_tables;
	}
}
