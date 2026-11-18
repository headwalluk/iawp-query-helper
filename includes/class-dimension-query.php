<?php
/**
 * Base class for breakdowns by a session attribute: referrer, country, device, campaign.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Groups sessions by one attribute and counts traffic per group, as the dashboard's breakdown tables do.
 *
 * Two statements per range: counts for one page of groups, then average session length for those
 * groups only. See docs/how-it-works.md for the metric definitions.
 *
 * @since 0.2.0
 */
abstract class Dimension_Query extends Query {

	/**
	 * Metrics reported per group, and in `previous` and `change_percent`.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	protected const METRICS = array( 'views', 'visitors', 'sessions', 'bounces', 'bounce_rate_percent', 'views_per_session', 'average_session_seconds' );

	/**
	 * Metrics a caller may sort by.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	protected const SORTABLE_METRICS = array( METRIC_VIEWS, METRIC_VISITORS, METRIC_SESSIONS );

	/**
	 * SQL expression for the group key, such as `sessions.country_id`.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract public function get_group_column( Query_Context $context ): string;

	/**
	 * JOIN clauses from `sessions` to the lookup tables. Must not reference `views`.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract public function get_join_sql( Query_Context $context ): string;

	/**
	 * Label columns to select, as alias => SQL expression on the joined tables.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, string>
	 */
	abstract public function get_label_columns( Query_Context $context ): array;

	/**
	 * Label alias that breaks ties in the ranking, as the dashboard's table does.
	 *
	 * @since 0.5.1
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	public function get_tie_breaker( Query_Context $context ): string {
		return (string) array_key_first( $this->get_label_columns( $context ) );
	}

	/**
	 * Schema for the label fields of a row.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract public function get_label_properties(): array;

	/**
	 * Extra WHERE conditions and their values, beyond the group key not being NULL.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>} SQL starting with AND, or empty, and its values.
	 */
	public function get_filter( Query_Context $context ): array {
		return array( '', array() );
	}

	/**
	 * Turn the label columns of a database row into output fields.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $row     Database row.
	 * @param Query_Context        $context Validated input.
	 * @return array<string, mixed>
	 */
	public function shape_labels( array $row, Query_Context $context ): array {
		$labels = array();

		foreach ( array_keys( $this->get_label_columns( $context ) ) as $alias ) {
			$labels[ $alias ] = isset( $row[ $alias ] ) ? (string) $row[ $alias ] : null;
		}

		return $labels;
	}

	/**
	 * Results are paged.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return true;
	}

	/**
	 * Sort order, plus any properties a subclass adds.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'orderby' => array(
				'type'        => 'string',
				'enum'        => self::SORTABLE_METRICS,
				'default'     => METRIC_VIEWS,
				'description' => __( 'Metric to rank by, highest first.', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * Row schema: labels, metrics and comparison fields.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = $this->get_metric_properties();

		return array(
			'type'       => 'object',
			'properties' => array_merge(
				$this->get_label_properties(),
				$metric_properties,
				$this->get_comparison_properties( $metric_properties )
			),
		);
	}

	/**
	 * Rank groups for the range, then fetch the same groups for the comparison range.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$per_page = $context->get_per_page();
		$current  = $this->get_groups( $context, $context->get_range(), array(), $per_page + 1, $context->get_offset() );
		$previous = array();

		if ( is_wp_error( $current ) ) {
			$result = $current;
		} else {
			$has_more   = count( $current ) > $per_page;
			$current    = array_slice( $current, 0, $per_page, true );
			$comparison = $context->get_comparison();

			if ( $comparison instanceof Date_Range && ! empty( $current ) ) {
				$previous = $this->get_groups( $context, $comparison, array_keys( $current ), count( $current ), 0 );
			}

			if ( is_wp_error( $previous ) ) {
				$result = $previous;
			} else {
				$result = array(
					'rows'     => $this->shape_rows( $current, $previous, $comparison instanceof Date_Range, $context ),
					'has_more' => $has_more,
				);
			}
		}

		return $result;
	}

	/**
	 * Schema of the metric fields.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_metric_properties(): array {
		return array_merge(
			$this->get_integer_properties( array( 'views', 'visitors', 'sessions', 'bounces' ) ),
			array(
				'bounce_rate_percent'     => array( 'type' => 'number' ),
				'views_per_session'       => array( 'type' => 'number' ),
				'average_session_seconds' => array( 'type' => array( 'integer', 'null' ) ),
			)
		);
	}

	/**
	 * Counts and average session length per group, keyed by group key.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range to count.
	 * @param array         $group_keys Only these groups; empty for a ranked page of all groups.
	 * @param int           $limit      Rows to fetch.
	 * @param int           $offset     Rows to skip.
	 * @return array<string, array<string, mixed>>|\WP_Error
	 */
	public function get_groups( Query_Context $context, Date_Range $range, array $group_keys, int $limit, int $offset ): array|\WP_Error {
		$count_rows = $this->get_count_rows( $context, $range, $group_keys, $limit, $offset );

		$groups = array();

		if ( ! is_wp_error( $count_rows ) ) {
			foreach ( $count_rows as $count_row ) {
				$groups[ (string) $count_row['group_key'] ] = $count_row;
			}
		}

		$durations = ( is_wp_error( $count_rows ) || empty( $groups ) ) ? array() : $this->get_durations( $context, $range, array_keys( $groups ) );

		if ( is_wp_error( $count_rows ) ) {
			$result = $count_rows;
		} elseif ( is_wp_error( $durations ) ) {
			$result = $durations;
		} else {
			foreach ( $groups as $group_key => $group ) {
				$groups[ $group_key ]['average_session_duration'] = $durations[ $group_key ] ?? null;
			}

			$result = $groups;
		}

		return $result;
	}

	/**
	 * One page of groups with views, visitors, sessions and bounces.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range to count.
	 * @param array         $group_keys Only these groups; empty for all.
	 * @param int           $limit      Rows to fetch.
	 * @param int           $offset     Rows to skip.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function get_count_rows( Query_Context $context, Date_Range $range, array $group_keys, int $limit, int $offset ): array|\WP_Error {
		global $wpdb;

		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$group_column   = $this->get_group_column( $context );
		$join_sql       = $this->get_join_sql( $context );
		$orderby        = in_array( $context->get_param( 'orderby' ), self::SORTABLE_METRICS, true ) ? (string) $context->get_param( 'orderby' ) : METRIC_VIEWS;
		$tie_breaker    = $this->get_tie_breaker( $context );

		list( $filter_sql, $values ) = $this->get_filter( $context );

		$label_sql = '';

		foreach ( $this->get_label_columns( $context ) as $alias => $expression ) {
			$label_sql .= sprintf( 'MAX(%s) AS %s, ', $expression, $alias );
		}

		$values = array_merge( array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() ), $values );

		if ( ! empty( $group_keys ) ) {
			$filter_sql .= sprintf( ' AND %s IN (%s)', $group_column, $this->get_integer_placeholders( count( $group_keys ) ) );
			$values      = array_merge( $values, array_map( 'intval', $group_keys ) );
		}

		$values[] = $limit;
		$values[] = $offset;

		// Every join is to-one from sessions, so COUNT(*) counts views.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; group, join, label and filter SQL are fixed per query class with only placeholders for values; $orderby is whitelisted.
		$sql = $wpdb->prepare(
			"SELECT {$group_column} AS group_key,
				{$label_sql}
				COUNT(*) AS views,
				COUNT(DISTINCT sessions.visitor_id) AS visitors,
				COUNT(DISTINCT sessions.session_id) AS sessions,
				COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL)) AS bounces
			FROM {$sessions_table} AS sessions
			INNER JOIN {$views_table} AS views ON views.session_id = sessions.session_id
			{$join_sql}
			WHERE sessions.created_at BETWEEN %s AND %s
				AND views.viewed_at BETWEEN %s AND %s
				AND {$group_column} IS NOT NULL
				{$filter_sql}
			GROUP BY {$group_column}
			ORDER BY {$orderby} DESC, {$tie_breaker} ASC, group_key ASC
			LIMIT %d OFFSET %d",
			$values
		);
		// phpcs:enable

		return $this->runner->get_rows( $sql );
	}

	/**
	 * Average session length per group, for sessions with at least one view in the range.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Groups to measure.
	 * @return array<string, int>|\WP_Error Seconds by group key.
	 */
	private function get_durations( Query_Context $context, Date_Range $range, array $group_keys ): array|\WP_Error {
		global $wpdb;

		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$group_column   = $this->get_group_column( $context );
		$join_sql       = $this->get_join_sql( $context );
		$placeholders   = $this->get_integer_placeholders( count( $group_keys ) );

		$values = array_merge(
			array( $range->get_start_utc(), $range->get_end_utc() ),
			array_map( 'intval', $group_keys ),
			array( $range->get_start_utc(), $range->get_end_utc() )
		);

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; group and join SQL are fixed per query class.
		$rows = $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT {$group_column} AS group_key,
					AVG(TIMESTAMPDIFF(SECOND, sessions.created_at, sessions.ended_at)) AS average_session_duration
				FROM {$sessions_table} AS sessions
				{$join_sql}
				WHERE sessions.created_at BETWEEN %s AND %s
					AND {$group_column} IN ({$placeholders})
					AND EXISTS (
						SELECT 1 FROM {$views_table} AS views
						WHERE views.session_id = sessions.session_id
							AND views.viewed_at BETWEEN %s AND %s
					)
				GROUP BY {$group_column}",
				$values
			)
		);
		// phpcs:enable

		$durations = array();

		if ( ! is_wp_error( $rows ) ) {
			foreach ( $rows as $row ) {
				// The dashboard truncates the average to whole seconds.
				$durations[ (string) $row['group_key'] ] = is_null( $row['average_session_duration'] ) ? null : (int) $row['average_session_duration'];
			}
		}

		return is_wp_error( $rows ) ? $rows : $durations;
	}

	/**
	 * Shape groups for output, attaching comparison figures by group key.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, array<string, mixed>> $current      Groups for the range.
	 * @param array<string, array<string, mixed>> $previous     Groups for the comparison range.
	 * @param bool                                $is_comparing Whether a comparison was requested.
	 * @param Query_Context                       $context      Validated input.
	 * @return array<int, array<string, mixed>>
	 */
	private function shape_rows( array $current, array $previous, bool $is_comparing, Query_Context $context ): array {
		$shaped = array();

		foreach ( $current as $group_key => $group ) {
			$metrics = $this->get_metric_values( $group );
			$prior   = null;

			if ( $is_comparing ) {
				$prior = isset( $previous[ $group_key ] ) ? $this->get_metric_values( $previous[ $group_key ] ) : $this->get_metric_values( array() );
			}

			$shaped[] = array_merge(
				$this->shape_labels( $group, $context ),
				$metrics,
				array(
					'previous'       => $prior,
					'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, self::METRICS ) : null,
				)
			);
		}

		return $shaped;
	}

	/**
	 * Metric values of a group, with rates calculated and rounded as on the dashboard.
	 *
	 * @since 0.2.0
	 *
	 * @param array<string, mixed> $group Group row.
	 * @return array<string, int|float|null>
	 */
	private function get_metric_values( array $group ): array {
		$views    = (int) ( $group['views'] ?? 0 );
		$sessions = (int) ( $group['sessions'] ?? 0 );
		$bounces  = (int) ( $group['bounces'] ?? 0 );

		return array(
			'views'                   => $views,
			'visitors'                => (int) ( $group['visitors'] ?? 0 ),
			'sessions'                => $sessions,
			'bounces'                 => $bounces,
			'bounce_rate_percent'     => round_sql_value( sql_divide( $bounces, $sessions, 100 ) ),
			'views_per_session'       => round_sql_value( sql_divide( $views, $sessions ) ),
			'average_session_seconds' => $group['average_session_duration'] ?? null,
		);
	}
}
