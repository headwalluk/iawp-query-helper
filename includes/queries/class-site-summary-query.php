<?php
/**
 * Query: site-wide headline numbers for a range.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * The figures at the top of the IAWP dashboard: views, visitors, sessions, bounce rate, session length.
 *
 * @since 0.1.0
 */
class Site_Summary_Query extends Query {

	/**
	 * Metrics reported in `previous` and `change_percent`.
	 *
	 * @since 0.1.0
	 * @var string[]
	 */
	private const COMPARED_METRICS = array( 'views', 'visitors', 'sessions', 'bounces', 'bounce_rate_percent', 'average_session_seconds', 'views_per_session' );

	/**
	 * Query name.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'site-summary';
	}

	/**
	 * Label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Site summary', 'ability label; website traffic totals', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Site-wide totals from Independent Analytics for a date range: views, unique visitors, sessions, bounces, bounce rate, average session length and views per session. Figures match the Independent Analytics dashboard for the same range. Use compare=previous_year with period=year_to_date for year-on-year. Returns one row.', 'iawp-query-helper' );
	}

	/**
	 * Row schema.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = $this->get_metric_properties();

		return array(
			'type'       => 'object',
			'properties' => array_merge( $metric_properties, $this->get_comparison_properties( $metric_properties ) ),
		);
	}

	/**
	 * Run the summary for the range, and for the comparison range if requested.
	 *
	 * @since 0.1.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$current    = $this->get_summary( $context->get_range() );
		$comparison = $context->get_comparison();
		$previous   = null;

		if ( ! is_wp_error( $current ) && $comparison instanceof Date_Range ) {
			$previous = $this->get_summary( $comparison );
		}

		if ( is_wp_error( $current ) ) {
			$result = $current;
		} elseif ( is_wp_error( $previous ) ) {
			$result = $previous;
		} else {
			$row = $current;

			$row['previous']       = $previous;
			$row['change_percent'] = is_array( $previous ) ? percent_changes( $current, $previous, self::COMPARED_METRICS ) : null;

			$result = array( 'rows' => array( $row ) );
		}

		return $result;
	}

	/**
	 * Schema of the metric fields.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	private function get_metric_properties(): array {
		return array_merge(
			$this->get_integer_properties( array( 'views', 'visitors', 'sessions', 'bounces', 'average_session_seconds' ) ),
			array(
				'bounce_rate_percent' => array(
					'type'        => 'number',
					'description' => __( 'Single-view sessions as a percentage of sessions, rounded as on the dashboard.', 'iawp-query-helper' ),
				),
				'views_per_session'   => array( 'type' => 'number' ),
			)
		);
	}

	/**
	 * Headline numbers for one range, calculated as IAWP's dashboard does.
	 *
	 * A session counts when it started in the range and has at least one view in the range.
	 *
	 * @since 0.1.0
	 *
	 * @param Date_Range $range Range.
	 * @return array<string, int|float>|\WP_Error
	 */
	private function get_summary( Date_Range $range ): array|\WP_Error {
		global $wpdb;

		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$start_utc      = $range->get_start_utc();
		$end_utc        = $range->get_end_utc();

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table names come from $wpdb->prefix and constants.
		$counts_rows = $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT COUNT(*) AS views,
					COUNT(DISTINCT sessions.visitor_id) AS visitors,
					COUNT(DISTINCT sessions.session_id) AS sessions,
					COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL)) AS bounces
				FROM {$sessions_table} AS sessions
				INNER JOIN {$views_table} AS views ON views.session_id = sessions.session_id
				WHERE sessions.created_at BETWEEN %s AND %s
					AND views.viewed_at BETWEEN %s AND %s",
				$start_utc,
				$end_utc,
				$start_utc,
				$end_utc
			)
		);

		$session_rows = is_wp_error( $counts_rows ) ? $counts_rows : $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT IFNULL(SUM(sessions.total_views), 0) AS total_views,
					AVG(TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at)) AS average_session_duration
				FROM {$sessions_table} AS sessions
				WHERE sessions.created_at BETWEEN %s AND %s
					AND EXISTS (
						SELECT 1 FROM {$views_table} AS views
						WHERE views.session_id = sessions.session_id
							AND views.viewed_at BETWEEN %s AND %s
					)",
				$start_utc,
				$end_utc,
				$start_utc,
				$end_utc
			)
		);
		// phpcs:enable

		if ( is_wp_error( $session_rows ) ) {
			$result = $session_rows;
		} else {
			$counts       = $counts_rows[0] ?? array();
			$session_data = $session_rows[0] ?? array();
			$sessions     = (int) ( $counts['sessions'] ?? 0 );
			$bounces      = (int) ( $counts['bounces'] ?? 0 );

			$result = array(
				'views'                   => (int) ( $counts['views'] ?? 0 ),
				'visitors'                => (int) ( $counts['visitors'] ?? 0 ),
				'sessions'                => $sessions,
				'bounces'                 => $bounces,
				'bounce_rate_percent'     => percentage( $bounces, $sessions ),
				'average_session_seconds' => (int) round( (float) ( $session_data['average_session_duration'] ?? 0 ) ),
				'views_per_session'       => divide( (int) ( $session_data['total_views'] ?? 0 ), $sessions, 2 ),
			);
		}

		return $result;
	}
}
