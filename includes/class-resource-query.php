<?php
/**
 * Base class for page-level queries: every kind of page IAWP records.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Counts traffic per resource (post, archive, search page, 404...) as the dashboard's Pages table does.
 *
 * One statement per range. Joins to each session's first and last views are to-one, so entrances,
 * exits and bounces come out of the same pass as views. See docs/how-it-works.md.
 *
 * @since 0.3.0
 */
abstract class Resource_Query extends Query {

	/**
	 * Resource types IAWP records, as stored in `resources.resource`.
	 *
	 * @since 0.3.0
	 * @var string[]
	 */
	public const RESOURCE_TYPES = array( 'singular', 'home', 'post_type_archive', 'term_archive', 'author_archive', 'date_archive', 'search', '404', 'virtual_page' );

	/**
	 * Metrics reported per page, and in `previous` and `change_percent`.
	 *
	 * @since 0.3.0
	 * @var string[]
	 */
	protected const METRICS = array( 'views', 'visitors', 'sessions', 'entrances', 'exits', 'exit_percent', 'bounces', 'bounce_rate_percent', 'views_per_session', 'average_view_seconds' );

	/**
	 * Metrics a caller may sort by.
	 *
	 * @since 0.3.0
	 * @var string[]
	 */
	protected const SORTABLE_METRICS = array( METRIC_VIEWS, METRIC_VISITORS, METRIC_SESSIONS, 'entrances', 'exits' );

	/**
	 * The resource type this query is fixed to, or null for any.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	protected function get_fixed_resource_type(): ?string {
		return null;
	}

	/**
	 * Default sort metric.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	protected function get_default_orderby(): string {
		return METRIC_VIEWS;
	}

	/**
	 * Extra HAVING condition, such as `entrances > 0`, or an empty string.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	protected function get_having_sql(): string {
		return '';
	}

	/**
	 * Results are paged.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return true;
	}

	/**
	 * Sort order, plus the resource type and post type filters where they apply.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		$properties = array(
			'orderby' => array(
				'type'        => 'string',
				'enum'        => self::SORTABLE_METRICS,
				'default'     => $this->get_default_orderby(),
				'description' => __( 'Metric to rank by, highest first. entrances counts sessions that started on the page; exits counts sessions that ended on it.', 'iawp-query-helper' ),
			),
		);

		if ( is_null( $this->get_fixed_resource_type() ) ) {
			$properties['resource_type'] = array(
				'type'        => 'string',
				'enum'        => self::RESOURCE_TYPES,
				'description' => __( 'Only pages of this kind: singular (posts, pages, products), home, post_type_archive, term_archive (category and tag pages), author_archive, date_archive, search, 404 or virtual_page. Omit for all.', 'iawp-query-helper' ),
			);
			$properties['post_type']     = array(
				'type'        => 'string',
				'pattern'     => '^[a-z0-9_-]{1,20}$',
				'description' => __( 'Only singular pages of this post type, such as post or product.', 'iawp-query-helper' ),
			);
			$properties['url']           = array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'Only the page with exactly this absolute URL, as Independent Analytics recorded it.', 'iawp-query-helper' ),
			);
		}

		return $properties;
	}

	/**
	 * Row schema.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = $this->get_metric_properties();

		return array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'resource_type' => array( 'type' => 'string' ),
					'title'         => array( 'type' => array( 'string', 'null' ) ),
					'url'           => array( 'type' => array( 'string', 'null' ) ),
					'path'          => array( 'type' => array( 'string', 'null' ) ),
					'post_id'       => array(
						'type'        => array( 'integer', 'null' ),
						'description' => __( 'Post ID for singular pages; null otherwise.', 'iawp-query-helper' ),
					),
					'post_type'     => array( 'type' => array( 'string', 'null' ) ),
					'search_query'  => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'What the visitor searched for, on search pages.', 'iawp-query-helper' ),
					),
					'not_found_url' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'The requested path, on 404 pages.', 'iawp-query-helper' ),
					),
				),
				$metric_properties,
				$this->get_comparison_properties( $metric_properties )
			),
		);
	}

	/**
	 * Rank pages for the range, then fetch the same pages for the comparison range.
	 *
	 * @since 0.3.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$post_type = (string) $context->get_param( 'post_type', '' );

		if ( '' !== $post_type && ! post_type_exists( $post_type ) ) {
			$result = new \WP_Error(
				ERROR_INVALID_PARAM,
				sprintf(
					/* translators: %s: post type name. */
					__( 'There is no post type called %s on this site.', 'iawp-query-helper' ),
					$post_type
				),
				array( 'status' => 400 )
			);
		} else {
			$per_page = $context->get_per_page();
			$current  = $this->get_resources( $context, $context->get_range(), array(), $per_page + 1, $context->get_offset() );
			$result   = is_wp_error( $current ) ? $current : $this->add_comparison( $context, array_slice( $current, 0, $per_page, true ), count( $current ) > $per_page );
		}

		return $result;
	}

	/**
	 * Counts per resource, keyed by resource ID, ranked by the chosen metric.
	 *
	 * @since 0.3.0
	 *
	 * @param Query_Context $context      Validated input.
	 * @param Date_Range    $range        Range to count.
	 * @param int[]         $resource_ids Only these resources; empty for a ranked page of all.
	 * @param int           $limit        Rows to fetch.
	 * @param int           $offset       Rows to skip.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	public function get_resources( Query_Context $context, Date_Range $range, array $resource_ids, int $limit, int $offset ): array|\WP_Error {
		global $wpdb;

		$views_table     = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table  = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$resources_table = $this->environment->get_table( IAWP_TABLE_RESOURCES );
		$orderby         = in_array( $context->get_param( 'orderby' ), self::SORTABLE_METRICS, true ) ? (string) $context->get_param( 'orderby' ) : $this->get_default_orderby();
		$resource_type   = $this->get_fixed_resource_type() ?? (string) $context->get_param( 'resource_type', '' );
		$post_type       = (string) $context->get_param( 'post_type', '' );
		$url             = (string) $context->get_param( 'url', '' );

		$filters = array();
		$values  = array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() );

		if ( '' !== $resource_type ) {
			$filters[] = 'AND resources.resource = %s';
			$values[]  = $resource_type;
		}

		if ( '' !== $post_type ) {
			$filters[] = 'AND resources.cached_type = %s';
			$values[]  = $post_type;
		}

		if ( '' !== $url ) {
			$filters[] = 'AND resources.cached_url = %s';
			$values[]  = $url;
		}

		if ( ! empty( $resource_ids ) ) {
			$filters[] = 'AND views.resource_id IN (' . $this->get_integer_placeholders( count( $resource_ids ) ) . ')';
			$values    = array_merge( $values, array_map( 'intval', $resource_ids ) );
		}

		$values[] = $limit;
		$values[] = $offset;

		$filter_sql = implode( ' ', $filters );
		$having_sql = $this->get_having_sql();
		$having_sql = '' === $having_sql ? '' : 'HAVING ' . $having_sql;

		// The initial and final view joins are to-one, so each row is still one view.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; $filter_sql holds only placeholders; $orderby and $having_sql are fixed by the query class.
		$sql = $wpdb->prepare(
			"SELECT views.resource_id AS resource_id,
				MAX(resources.resource) AS resource_type,
				MAX(resources.cached_title) AS title,
				MAX(resources.cached_url) AS url,
				MAX(resources.singular_id) AS post_id,
				MAX(resources.cached_type) AS post_type,
				MAX(resources.search_query) AS search_query,
				MAX(resources.not_found_url) AS not_found_url,
				COUNT(*) AS views,
				COUNT(DISTINCT sessions.visitor_id) AS visitors,
				COUNT(DISTINCT sessions.session_id) AS sessions,
				COUNT(DISTINCT IF(views.resource_id = initial_view.resource_id, sessions.session_id, NULL)) AS entrances,
				COUNT(DISTINCT IF(views.resource_id = final_view.resource_id OR (views.resource_id = initial_view.resource_id AND sessions.final_view_id IS NULL), sessions.session_id, NULL)) AS exits,
				COUNT(DISTINCT IF(sessions.final_view_id IS NULL, sessions.session_id, NULL)) AS bounces,
				AVG(TIMESTAMPDIFF(SECOND, views.viewed_at, views.next_viewed_at)) AS average_view_duration
			FROM {$views_table} AS views
			INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
			INNER JOIN {$resources_table} AS resources ON resources.id = views.resource_id
			LEFT JOIN {$views_table} AS initial_view ON initial_view.id = sessions.initial_view_id
			LEFT JOIN {$views_table} AS final_view ON final_view.id = sessions.final_view_id
			WHERE views.viewed_at BETWEEN %s AND %s
				AND sessions.created_at BETWEEN %s AND %s
				{$filter_sql}
			GROUP BY views.resource_id
			{$having_sql}
			ORDER BY {$orderby} DESC, title ASC, views.resource_id ASC
			LIMIT %d OFFSET %d",
			$values
		);
		// phpcs:enable

		$rows   = $this->runner->get_rows( $sql );
		$result = $rows;

		if ( ! is_wp_error( $rows ) ) {
			$result = array();

			foreach ( $rows as $row ) {
				$result[ (int) $row['resource_id'] ] = $row;
			}
		}

		return $result;
	}

	/**
	 * Shape one database row's labels for output.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, mixed>
	 */
	public function shape_labels( array $row ): array {
		$url = isset( $row['url'] ) ? (string) $row['url'] : null;

		return redact_not_found_labels(
			array(
				'resource_type' => (string) ( $row['resource_type'] ?? '' ),
				'title'         => isset( $row['title'] ) ? (string) $row['title'] : null,
				'url'           => $url,
				'path'          => is_null( $url ) ? null : get_url_path( $url ),
				'post_id'       => isset( $row['post_id'] ) ? (int) $row['post_id'] : null,
				'post_type'     => isset( $row['post_type'] ) ? (string) $row['post_type'] : null,
				'search_query'  => isset( $row['search_query'] ) ? (string) $row['search_query'] : null,
				'not_found_url' => isset( $row['not_found_url'] ) ? (string) $row['not_found_url'] : null,
			)
		);
	}

	/**
	 * Metric values of a row, with rates calculated as the dashboard calculates them.
	 *
	 * @since 0.3.0
	 *
	 * @param array<string, mixed> $row Database row, or empty for zeros.
	 * @return array<string, int|float|null>
	 */
	public function get_metric_values( array $row ): array {
		$views    = (int) ( $row['views'] ?? 0 );
		$sessions = (int) ( $row['sessions'] ?? 0 );
		$exits    = (int) ( $row['exits'] ?? 0 );
		$bounces  = (int) ( $row['bounces'] ?? 0 );
		$duration = $row['average_view_duration'] ?? null;

		return array(
			'views'                => $views,
			'visitors'             => (int) ( $row['visitors'] ?? 0 ),
			'sessions'             => $sessions,
			'entrances'            => (int) ( $row['entrances'] ?? 0 ),
			'exits'                => $exits,
			'exit_percent'         => round_sql_value( sql_divide( $exits, $views, 100 ) ),
			'bounces'              => $bounces,
			'bounce_rate_percent'  => round_sql_value( sql_divide( $bounces, $sessions, 100 ) ),
			'views_per_session'    => round_sql_value( sql_divide( $views, $sessions ) ),
			// The dashboard truncates the average to whole seconds.
			'average_view_seconds' => is_null( $duration ) ? null : (int) $duration,
		);
	}

	/**
	 * Schema of the metric fields.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_metric_properties(): array {
		return array_merge(
			$this->get_integer_properties( array( 'views', 'visitors', 'sessions', 'entrances', 'exits', 'bounces' ) ),
			array(
				'exit_percent'         => array(
					'type'        => 'number',
					'description' => __( 'Exits as a percentage of views.', 'iawp-query-helper' ),
				),
				'bounce_rate_percent'  => array( 'type' => 'number' ),
				'views_per_session'    => array( 'type' => 'number' ),
				'average_view_seconds' => array(
					'type'        => array( 'integer', 'null' ),
					'description' => __( 'Average time on the page before the next view in the same session; null when no view had a next view.', 'iawp-query-helper' ),
				),
			)
		);
	}

	/**
	 * Attach comparison figures and shape the rows.
	 *
	 * @since 0.3.0
	 *
	 * @param Query_Context                    $context  Validated input.
	 * @param array<int, array<string, mixed>> $current  Rows for the range, keyed by resource ID.
	 * @param bool                             $has_more Whether more rows follow.
	 * @return array<string, mixed>|\WP_Error
	 */
	protected function add_comparison( Query_Context $context, array $current, bool $has_more ): array|\WP_Error {
		$comparison = $context->get_comparison();
		$previous   = array();

		if ( $comparison instanceof Date_Range && ! empty( $current ) ) {
			$previous = $this->get_resources( $context, $comparison, array_keys( $current ), count( $current ), 0 );
		}

		if ( is_wp_error( $previous ) ) {
			$result = $previous;
		} else {
			$rows = array();

			foreach ( $current as $resource_id => $row ) {
				$metrics = $this->get_metric_values( $row );
				$prior   = $comparison instanceof Date_Range ? $this->get_metric_values( $previous[ $resource_id ] ?? array() ) : null;

				$rows[] = array_merge(
					$this->shape_labels( $row ),
					$metrics,
					array(
						'previous'       => $prior,
						'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, self::METRICS ) : null,
					)
				);
			}

			$result = array(
				'rows'     => $rows,
				'has_more' => $has_more,
			);
		}

		return $result;
	}
}
