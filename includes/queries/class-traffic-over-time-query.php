<?php
/**
 * Query: views, visitors and sessions per day, week or month.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * A time series bucketed as the IAWP dashboard chart buckets it: by the day each session started.
 *
 * @since 0.1.0
 */
class Traffic_Over_Time_Query extends Query {

	/**
	 * Metrics reported per bucket.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private const METRICS = array( METRIC_VIEWS, METRIC_VISITORS, METRIC_SESSIONS );

	/**
	 * Query name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'traffic-over-time';
	}

	/**
	 * Label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Traffic over time', 'ability label; website visits per day, week or month', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Views, unique visitors and sessions per day, week or month across a date range, from Independent Analytics, matching the dashboard chart. Every bucket in the range is present, with zeros where there was no traffic. Visitors are unique within each bucket and must not be summed across buckets; use site-summary for range totals. With compare, previous_rows holds the same series for the comparison range.', 'iawp-query-helper' );
	}

	/**
	 * The comparison is a separate series.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	protected function has_previous_rows(): bool {
		return true;
	}

	/**
	 * Interval.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'interval' => array(
				'type'        => 'string',
				'enum'        => array( INTERVAL_DAILY, INTERVAL_WEEKLY, INTERVAL_MONTHLY ),
				'default'     => INTERVAL_DAILY,
				'description' => __( 'Bucket size. Weeks start on the day set in Independent Analytics.', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * Row schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'period_start' => array(
						'type'        => 'string',
						'description' => __( 'First day of the bucket, Y-m-d, site timezone.', 'iawp-query-helper' ),
					),
				),
				$this->get_integer_properties( self::METRICS )
			),
		);
	}

	/**
	 * Run the series for the range, and for the comparison range if requested.
	 *
	 * @since 0.1.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$interval   = (string) $context->get_param( 'interval', INTERVAL_DAILY );
		$current    = $this->get_series( $context->get_range(), $interval );
		$comparison = $context->get_comparison();
		$previous   = null;

		if ( ! is_wp_error( $current ) && $comparison instanceof Date_Range ) {
			$previous = $this->get_series( $comparison, $interval );
		}

		if ( is_wp_error( $current ) ) {
			$result = $current;
		} elseif ( is_wp_error( $previous ) ) {
			$result = $previous;
		} else {
			$result = array(
				'rows'          => $current,
				'previous_rows' => $previous,
			);
		}

		return $result;
	}

	/**
	 * One series of buckets for a range, zero-filled.
	 *
	 * @since 0.1.0
	 *
	 * @param Date_Range $range    Range.
	 * @param string     $interval One of the INTERVAL_* constants.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function get_series( Date_Range $range, string $interval ): array|\WP_Error {
		global $wpdb;

		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$site_offset    = $this->environment->get_site_offset();

		// CONVERT_TZ() with numeric offsets needs no timezone tables. IAWP uses the current
		// offset for every bucket, so a bucket either side of a DST change is off by an hour there too.
		$local_created = "CONVERT_TZ(sessions.created_at, '+00:00', %s)";

		if ( INTERVAL_WEEKLY === $interval ) {
			$bucket_sql    = "DATE(SUBDATE({$local_created}, MOD(DAYOFWEEK({$local_created}) - %d + 7, 7)))";
			$bucket_values = array( $site_offset, $site_offset, $this->environment->get_week_start_day() + 1 );
		} elseif ( INTERVAL_MONTHLY === $interval ) {
			$bucket_sql    = "DATE_FORMAT({$local_created}, '%%Y-%%m-01')";
			$bucket_values = array( $site_offset );
		} else {
			$bucket_sql    = "DATE({$local_created})";
			$bucket_values = array( $site_offset );
		}

		$values = array_merge( $bucket_values, array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() ) );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; $bucket_sql holds only placeholders and fixed SQL.
		$rows = $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT {$bucket_sql} AS period_start,
					COUNT(*) AS views,
					COUNT(DISTINCT sessions.visitor_id) AS visitors,
					COUNT(DISTINCT sessions.session_id) AS sessions
				FROM {$sessions_table} AS sessions
				INNER JOIN {$views_table} AS views ON views.session_id = sessions.session_id
				WHERE sessions.created_at BETWEEN %s AND %s
					AND views.viewed_at BETWEEN %s AND %s
				GROUP BY period_start
				ORDER BY period_start ASC",
				$values
			)
		);
		// phpcs:enable

		if ( is_wp_error( $rows ) ) {
			$result = $rows;
		} else {
			$result = $this->fill_buckets( $rows, $this->get_bucket_starts( $range, $interval ) );
		}

		return $result;
	}

	/**
	 * Merge database rows into the full list of buckets, zero-filling the gaps.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $rows          Database rows.
	 * @param string[]                         $bucket_starts Every bucket start, Y-m-d.
	 * @return array<int, array<string, mixed>>
	 */
	private function fill_buckets( array $rows, array $bucket_starts ): array {
		$rows_by_bucket = array();

		foreach ( $rows as $row ) {
			$rows_by_bucket[ substr( (string) $row['period_start'], 0, 10 ) ] = $row;
		}

		$series = array();

		foreach ( $bucket_starts as $bucket_start ) {
			$row    = $rows_by_bucket[ $bucket_start ] ?? array();
			$bucket = array( 'period_start' => $bucket_start );

			foreach ( self::METRICS as $metric ) {
				$bucket[ $metric ] = (int) ( $row[ $metric ] ?? 0 );
			}

			$series[] = $bucket;
		}

		return $series;
	}

	/**
	 * The first day of every bucket that overlaps the range.
	 *
	 * @since 0.1.0
	 *
	 * @param Date_Range $range    Range.
	 * @param string     $interval One of the INTERVAL_* constants.
	 * @return string[]
	 */
	private function get_bucket_starts( Date_Range $range, string $interval ): array {
		$first_day = $range->get_start_day();

		if ( INTERVAL_WEEKLY === $interval ) {
			$days_since_start = ( (int) $first_day->format( 'w' ) - $this->environment->get_week_start_day() + 7 ) % 7;
			$bucket_day       = $first_day->modify( sprintf( '-%d days', $days_since_start ) );
			$step             = '+7 days';
		} elseif ( INTERVAL_MONTHLY === $interval ) {
			$bucket_day = $first_day->modify( 'first day of this month' );
			$step       = '+1 month';
		} else {
			$bucket_day = $first_day;
			$step       = '+1 day';
		}

		$bucket_starts = array();

		while ( $bucket_day <= $range->get_end_day() ) {
			$bucket_starts[] = $bucket_day->format( DATE_FORMAT );
			$bucket_day      = $bucket_day->modify( $step );
		}

		return $bucket_starts;
	}
}
