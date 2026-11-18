<?php
/**
 * Base class for form submission breakdowns. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Ranks groups (pages, referrers, campaigns) by form submissions, then adds their traffic.
 *
 * A submission belongs to the view it was made from, and counts when that view and its session's
 * start are in the range, as on the dashboard's tables. Traffic and labels come from the matching
 * traffic query.
 *
 * @since 0.5.0
 */
abstract class Form_Breakdown_Query extends Query {

	/**
	 * Metrics reported in `previous` and `change_percent`.
	 *
	 * @since 0.5.0
	 * @var string[]
	 */
	private const METRICS = array( 'visitors', 'sessions', 'submissions', 'conversion_rate_percent' );

	/**
	 * Group key expression, reachable from form_submissions → views → sessions.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract protected function get_group_column( Query_Context $context ): string;

	/**
	 * JOIN clauses from `sessions` that the group key and filters need.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract protected function get_join_sql( Query_Context $context ): string;

	/**
	 * Extra WHERE conditions and values.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	abstract protected function get_filter( Query_Context $context ): array;

	/**
	 * Traffic rows for the given groups, keyed by group key.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Groups.
	 * @return array<string|int, array<string, mixed>>|\WP_Error
	 */
	abstract protected function get_traffic( Query_Context $context, Date_Range $range, array $group_keys ): array|\WP_Error;

	/**
	 * Output labels from a traffic row.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @param Query_Context        $context     Validated input.
	 * @return array<string, mixed>
	 */
	abstract protected function shape_labels( array $traffic_row, Query_Context $context ): array;

	/**
	 * Schema of the label fields.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function get_label_properties(): array;

	/**
	 * Only with a licensed copy of IAWP Pro.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool {
		return true;
	}

	/**
	 * Results are paged.
	 *
	 * @since 0.5.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return true;
	}

	/**
	 * Optional form filter.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'form_id' => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Only count submissions of this form (form_id from the form-submissions query).', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * Row schema.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = array_merge(
			$this->get_integer_properties( array( 'visitors', 'sessions', 'submissions' ) ),
			array(
				'conversion_rate_percent' => array(
					'type'        => 'number',
					'description' => __( 'Submissions as a percentage of this row\'s visitors.', 'iawp-query-helper' ),
				),
			)
		);

		return array(
			'type'       => 'object',
			'properties' => array_merge( $this->get_label_properties(), $metric_properties, $this->get_comparison_properties( $metric_properties ) ),
		);
	}

	/**
	 * Rank groups by submissions, add traffic, and compare when asked.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$per_page    = $context->get_per_page();
		$submissions = $this->get_submission_counts( $context, $context->get_range(), array(), $per_page + 1, $context->get_offset() );
		$result      = $submissions;

		if ( ! is_wp_error( $submissions ) ) {
			$has_more    = count( $submissions ) > $per_page;
			$submissions = array_slice( $submissions, 0, $per_page, true );
			$group_keys  = array_keys( $submissions );
			$traffic     = empty( $group_keys ) ? array() : $this->get_traffic( $context, $context->get_range(), $group_keys );
			$comparison  = $context->get_comparison();
			$previous    = array( array(), array() );

			if ( ! is_wp_error( $traffic ) && $comparison instanceof Date_Range && ! empty( $group_keys ) ) {
				$previous_counts  = $this->get_submission_counts( $context, $comparison, $group_keys, count( $group_keys ), 0 );
				$previous_traffic = is_wp_error( $previous_counts ) ? $previous_counts : $this->get_traffic( $context, $comparison, $group_keys );
				$previous         = is_wp_error( $previous_traffic ) ? $previous_traffic : array( $previous_counts, $previous_traffic );
			}

			if ( is_wp_error( $traffic ) ) {
				$result = $traffic;
			} elseif ( is_wp_error( $previous ) ) {
				$result = $previous;
			} else {
				$result = array(
					'rows'     => $this->shape_rows( $context, $submissions, $traffic, $previous, $comparison instanceof Date_Range ),
					'has_more' => $has_more,
				);
			}
		}

		return $result;
	}

	/**
	 * Submissions per group, keyed by group key.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Only these groups; empty for a ranked page of all.
	 * @param int           $limit      Rows to fetch.
	 * @param int           $offset     Rows to skip.
	 * @return array<string|int, int>|\WP_Error
	 */
	private function get_submission_counts( Query_Context $context, Date_Range $range, array $group_keys, int $limit, int $offset ): array|\WP_Error {
		global $wpdb;

		$submissions_table = $this->environment->get_table( IAWP_TABLE_FORM_SUBMISSIONS );
		$views_table       = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table    = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$group_column      = $this->get_group_column( $context );
		$join_sql          = $this->get_join_sql( $context );
		$form_id           = (int) $context->get_param( 'form_id', 0 );

		list( $filter_sql, $filter_values ) = $this->get_filter( $context );

		$values = array_merge( array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() ), $filter_values );

		if ( $form_id > 0 ) {
			$filter_sql .= ' AND form_submissions.form_id = %d';
			$values[]    = $form_id;
		}

		if ( ! empty( $group_keys ) ) {
			$filter_sql .= sprintf( ' AND %s IN (%s)', $group_column, $this->get_integer_placeholders( count( $group_keys ) ) );
			$values      = array_merge( $values, array_map( 'intval', $group_keys ) );
		}

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; group, join and filter SQL fixed per query class.
		$sql = $wpdb->prepare(
			"SELECT {$group_column} AS group_key, COUNT(*) AS submissions
			FROM {$submissions_table} AS form_submissions
			INNER JOIN {$views_table} AS views ON views.id = form_submissions.view_id
			INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
			{$join_sql}
			WHERE views.viewed_at BETWEEN %s AND %s
				AND sessions.created_at BETWEEN %s AND %s
				AND {$group_column} IS NOT NULL
				{$filter_sql}
			GROUP BY {$group_column}
			ORDER BY submissions DESC, group_key ASC
			LIMIT %d OFFSET %d",
			$values
		);
		// phpcs:enable

		$rows   = $this->runner->get_rows( $sql );
		$result = $rows;

		if ( ! is_wp_error( $rows ) ) {
			$result = array();

			foreach ( $rows as $row ) {
				$result[ $row['group_key'] ] = (int) $row['submissions'];
			}
		}

		return $result;
	}

	/**
	 * Shape rows: labels from traffic, then traffic and submission metrics.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context      Validated input.
	 * @param array         $submissions  Submissions by group key.
	 * @param array         $traffic      Traffic rows by group key.
	 * @param array         $previous     Comparison submissions and traffic.
	 * @param bool          $is_comparing Whether comparing.
	 * @return array<int, array<string, mixed>>
	 */
	private function shape_rows( Query_Context $context, array $submissions, array $traffic, array $previous, bool $is_comparing ): array {
		$rows = array();

		foreach ( $submissions as $group_key => $count ) {
			$traffic_row = $traffic[ $group_key ] ?? array();
			$metrics     = $this->get_metrics( $count, $traffic_row );
			$prior       = $is_comparing ? $this->get_metrics( $previous[0][ $group_key ] ?? 0, $previous[1][ $group_key ] ?? array() ) : null;

			$rows[] = array_merge(
				$this->shape_labels( $traffic_row, $context ),
				$metrics,
				array(
					'previous'       => $prior,
					'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, self::METRICS ) : null,
				)
			);
		}

		return $rows;
	}

	/**
	 * Traffic and submission metrics for one group.
	 *
	 * @since 0.5.0
	 *
	 * @param int                  $submissions Submissions.
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @return array<string, int|float>
	 */
	private function get_metrics( int $submissions, array $traffic_row ): array {
		$visitors = (int) ( $traffic_row['visitors'] ?? 0 );

		return array(
			'visitors'                => $visitors,
			'sessions'                => (int) ( $traffic_row['sessions'] ?? 0 ),
			'submissions'             => $submissions,
			// The dashboard's tables report 0, not 100, when there are no visitors.
			'conversion_rate_percent' => 0 === $visitors ? 0.0 : round( $submissions / $visitors * 100, 2 ),
		);
	}
}
