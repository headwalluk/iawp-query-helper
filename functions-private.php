<?php
/**
 * Private helpers, namespaced to the plugin.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Log a routine trace message, only when WP_DEBUG is on.
 *
 * @since 0.1.0
 *
 * @param string $message Message to log.
 */
function log( string $message ): void {
	if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
		error_log( sprintf( '[%s] %s', PLUGIN_NAME, $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Debug tracing, WP_DEBUG only.
	}
}

/**
 * Log a genuine failure, always.
 *
 * @since 0.1.0
 *
 * @param string $message Message to log.
 */
function log_error( string $message ): void {
	error_log( sprintf( '[%s] ERROR: %s', PLUGIN_NAME, $message ) ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Failures must reach the server log.
}

/**
 * Whether the current user may run analytics queries.
 *
 * Mirrors IAWP's "view all analytics" check. Authored-only access is refused in v1.
 *
 * @since 0.1.0
 *
 * @return bool
 */
function can_current_user_query(): bool {
	$user = wp_get_current_user();

	$is_iawp_admin = current_user_can( 'setup_network' ) || in_array( 'administrator', (array) $user->roles, true );

	return $is_iawp_admin || current_user_can( IAWP_CAP_READ_ONLY ) || current_user_can( IAWP_CAP_FULL );
}

/**
 * Permission callback shared by the REST routes and the abilities.
 *
 * @since 0.1.0
 *
 * @return true|\WP_Error
 */
function check_query_permission(): true|\WP_Error {
	$result = true;

	if ( ! can_current_user_query() ) {
		$result = new \WP_Error(
			ERROR_FORBIDDEN,
			__( 'You need permission to view all Independent Analytics data.', 'iawp-query-helper' ),
			array( 'status' => is_user_logged_in() ? 403 : 401 )
		);
	}

	return $result;
}

/**
 * Read an integer from a filter, falling back to the default and clamping to bounds.
 *
 * @since 0.1.0
 *
 * @param string $filter_name   Filter to apply.
 * @param int    $default_value Value passed to the filter, and used if it returns garbage.
 * @param int    $minimum       Lowest allowed value.
 * @param int    $maximum       Highest allowed value.
 * @return int
 */
function get_filtered_int( string $filter_name, int $default_value, int $minimum, int $maximum ): int {
	$filtered = apply_filters( $filter_name, $default_value ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.DynamicHooknameFound -- Callers pass the prefixed FILTER_* constants.

	if ( is_numeric( $filtered ) ) {
		$value = (int) $filtered;
	} else {
		log_error( sprintf( 'Filter %s returned a non-numeric value; using %d.', $filter_name, $default_value ) );
		$value = $default_value;
	}

	return max( $minimum, min( $maximum, $value ) );
}

/**
 * Percentage as IAWP's dashboard computes it: 100 when only the denominator is zero.
 *
 * @since 0.1.0
 *
 * @param int|float $numerator   Part.
 * @param int|float $denominator Whole.
 * @param int       $precision   Decimal places.
 * @return float
 */
function percentage( int|float $numerator, int|float $denominator, int $precision = 0 ): float {
	if ( 0 == $denominator && $numerator > 0 ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- int and float zero are both zero.
		$result = 100.0;
	} elseif ( 0 == $denominator ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- As above.
		$result = 0.0;
	} else {
		$result = round( $numerator / $denominator * 100, $precision );
	}

	return (float) $result;
}

/**
 * Division as IAWP's dashboard computes it: 100 when only the denominator is zero.
 *
 * @since 0.1.0
 *
 * @param int|float $numerator   Dividend.
 * @param int|float $denominator Divisor.
 * @param int       $precision   Decimal places.
 * @return float
 */
function divide( int|float $numerator, int|float $denominator, int $precision = 0 ): float {
	if ( 0 == $denominator && $numerator > 0 ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- int and float zero are both zero.
		$result = 100.0;
	} elseif ( 0 == $denominator ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseEqual -- As above.
		$result = 0.0;
	} else {
		$result = round( $numerator / $denominator, $precision );
	}

	return (float) $result;
}

/**
 * Percentage change from a previous value, or null when there is no previous value to compare with.
 *
 * @since 0.1.0
 *
 * @param int|float $current  Current value.
 * @param int|float $previous Previous value.
 * @return float|null
 */
function percent_change( int|float $current, int|float $previous ): ?float {
	$result = null;

	if ( 0 != $previous ) { // phpcs:ignore Universal.Operators.StrictComparisons.LooseNotEqual -- int and float zero are both zero.
		$result = round( ( $current - $previous ) / $previous * 100, 1 );
	}

	return $result;
}

/**
 * Change from previous to current for each named metric.
 *
 * @since 0.1.0
 *
 * @param array<string, int|float> $current  Current values by metric.
 * @param array<string, int|float> $previous Previous values by metric.
 * @param string[]                 $metrics  Metric names to compare.
 * @return array<string, float|null>
 */
function percent_changes( array $current, array $previous, array $metrics ): array {
	$changes = array();

	foreach ( $metrics as $metric ) {
		$changes[ $metric ] = percent_change( $current[ $metric ] ?? 0, $previous[ $metric ] ?? 0 );
	}

	return $changes;
}

/**
 * The path of a URL plus any query string, such as `/?post_type=product&p=12`.
 *
 * A draft or unpublished post's URL carries its identity in the query string, so it is kept.
 *
 * @since 0.1.0
 *
 * @param string $url Absolute URL.
 * @return string
 */
function get_url_path( string $url ): string {
	$path  = (string) wp_parse_url( $url, PHP_URL_PATH );
	$query = (string) wp_parse_url( $url, PHP_URL_QUERY );

	if ( '' === $path ) {
		$path = '/';
	}

	return '' === $query ? $path : $path . '?' . $query;
}
