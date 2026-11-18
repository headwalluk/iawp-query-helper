<?php
/**
 * Query: posts, pages and other singular content ranked by traffic.
 *
 * @package IAWP_Query_Helper
 * @since 0.1.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Singular resources ranked by views, visitors or sessions, counted as on the dashboard's Pages table.
 *
 * @since 0.1.0
 */
class Top_Posts_Query extends Query {

	/**
	 * Metrics reported per post, and in `previous` and `change_percent`.
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
		return 'top-posts';
	}

	/**
	 * Label.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Top posts', 'ability label; most-visited blog posts and pages', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.1.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Posts, pages, products and other singular content ranked by views, visitors or sessions for a date range, from Independent Analytics. Each row has the post ID, title, post type, absolute URL and path; the URL is the key to match against Google Search Console page data. Figures match the dashboard\'s Pages table. Visitors are unique per row and must not be summed across rows. Paged: check meta.pagination.has_more.', 'iawp-query-helper' );
	}

	/**
	 * Results are paged.
	 *
	 * @since 0.1.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return true;
	}

	/**
	 * Filters and sort order.
	 *
	 * @since 0.1.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'post_type' => array(
				'type'        => 'string',
				'pattern'     => '^[a-z0-9_-]{1,20}$',
				'description' => __( 'Only this post type, such as post, page or product. Omit for all.', 'iawp-query-helper' ),
			),
			'category'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'Only posts in this category, by term ID.', 'iawp-query-helper' ),
			),
			'orderby'   => array(
				'type'        => 'string',
				'enum'        => self::METRICS,
				'default'     => METRIC_VIEWS,
				'description' => __( 'Metric to rank by, highest first.', 'iawp-query-helper' ),
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
		$metric_properties = $this->get_integer_properties( self::METRICS );

		return array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'post_id'   => array( 'type' => 'integer' ),
					'title'     => array( 'type' => 'string' ),
					'post_type' => array( 'type' => 'string' ),
					'url'       => array( 'type' => 'string' ),
					'path'      => array( 'type' => 'string' ),
				),
				$metric_properties,
				$this->get_comparison_properties( $metric_properties )
			),
		);
	}

	/**
	 * Rank posts for the range, then fetch the same posts' figures for the comparison range.
	 *
	 * @since 0.1.0
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
			$result = $this->get_ranked_rows( $context );
		}

		return $result;
	}

	/**
	 * The requested page of ranked rows, with comparison figures when asked for.
	 *
	 * @since 0.1.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	private function get_ranked_rows( Query_Context $context ): array|\WP_Error {
		$per_page    = $context->get_per_page();
		$ranked_rows = $this->get_counts( $context, $context->get_range(), array(), $per_page + 1, $context->get_offset() );

		if ( is_wp_error( $ranked_rows ) ) {
			$result = $ranked_rows;
		} else {
			$has_more    = count( $ranked_rows ) > $per_page;
			$ranked_rows = array_slice( $ranked_rows, 0, $per_page );
			$comparison  = $context->get_comparison();
			$previous    = array();

			if ( $comparison instanceof Date_Range && ! empty( $ranked_rows ) ) {
				$resource_ids = array_map( 'intval', array_column( $ranked_rows, 'resource_id' ) );
				$previous     = $this->get_counts( $context, $comparison, $resource_ids, count( $resource_ids ), 0 );
			}

			if ( is_wp_error( $previous ) ) {
				$result = $previous;
			} else {
				$result = array(
					'rows'     => $this->shape_rows( $ranked_rows, $previous, $comparison instanceof Date_Range ),
					'has_more' => $has_more,
				);
			}
		}

		return $result;
	}

	/**
	 * Views, visitors and sessions per singular resource, ranked by the chosen metric.
	 *
	 * @since 0.1.0
	 *
	 * @param Query_Context $context      Validated input.
	 * @param Date_Range    $range        Range to count.
	 * @param int[]         $resource_ids Only these resources; empty for all.
	 * @param int           $limit        Rows to fetch.
	 * @param int           $offset       Rows to skip.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function get_counts( Query_Context $context, Date_Range $range, array $resource_ids, int $limit, int $offset ): array|\WP_Error {
		global $wpdb;

		$views_table     = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table  = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$resources_table = $this->environment->get_table( IAWP_TABLE_RESOURCES );
		$post_type       = (string) $context->get_param( 'post_type', '' );
		$category_id     = (int) $context->get_param( 'category', 0 );
		$orderby         = in_array( $context->get_param( 'orderby' ), self::METRICS, true ) ? (string) $context->get_param( 'orderby' ) : METRIC_VIEWS;

		$filters = array();
		$values  = array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() );

		if ( '' !== $post_type ) {
			$filters[] = 'AND resources.cached_type = %s';
			$values[]  = $post_type;
		}

		if ( $category_id > 0 ) {
			// cached_category holds term IDs separated by ", ".
			$filters[] = "AND FIND_IN_SET(%d, REPLACE(resources.cached_category, ', ', ','))";
			$values[]  = $category_id;
		}

		if ( ! empty( $resource_ids ) ) {
			$filters[] = 'AND views.resource_id IN (' . $this->get_integer_placeholders( count( $resource_ids ) ) . ')';
			$values    = array_merge( $values, $resource_ids );
		}

		$values[] = $limit;
		$values[] = $offset;

		$filter_sql = implode( ' ', $filters );

		// MAX() on the resource columns keeps the GROUP BY valid under ONLY_FULL_GROUP_BY.
		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; $filter_sql holds only placeholders; $orderby is whitelisted.
		$sql = $wpdb->prepare(
			"SELECT views.resource_id AS resource_id,
				MAX(resources.singular_id) AS post_id,
				MAX(resources.cached_title) AS title,
				MAX(resources.cached_type) AS post_type,
				MAX(resources.cached_url) AS url,
				COUNT(*) AS views,
				COUNT(DISTINCT sessions.visitor_id) AS visitors,
				COUNT(DISTINCT sessions.session_id) AS sessions
			FROM {$views_table} AS views
			INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
			INNER JOIN {$resources_table} AS resources ON resources.id = views.resource_id
			WHERE views.viewed_at BETWEEN %s AND %s
				AND sessions.created_at BETWEEN %s AND %s
				AND resources.singular_id IS NOT NULL
				{$filter_sql}
			GROUP BY views.resource_id
			ORDER BY {$orderby} DESC, views.resource_id ASC
			LIMIT %d OFFSET %d",
			$values
		);
		// phpcs:enable

		return $this->runner->get_rows( $sql );
	}

	/**
	 * Shape database rows for output, attaching comparison figures by resource.
	 *
	 * @since 0.1.0
	 *
	 * @param array<int, array<string, mixed>> $ranked_rows  Rows for the range.
	 * @param array<int, array<string, mixed>> $previous     Rows for the comparison range.
	 * @param bool                             $is_comparing Whether a comparison was requested.
	 * @return array<int, array<string, mixed>>
	 */
	private function shape_rows( array $ranked_rows, array $previous, bool $is_comparing ): array {
		$previous_by_resource = array();

		foreach ( $previous as $previous_row ) {
			$previous_by_resource[ (int) $previous_row['resource_id'] ] = $this->get_metric_values( $previous_row );
		}

		$shaped = array();

		foreach ( $ranked_rows as $ranked_row ) {
			$url     = (string) ( $ranked_row['url'] ?? '' );
			$current = $this->get_metric_values( $ranked_row );
			$prior   = null;

			if ( $is_comparing ) {
				$prior = $previous_by_resource[ (int) $ranked_row['resource_id'] ] ?? array_fill_keys( self::METRICS, 0 );
			}

			$shaped[] = array_merge(
				array(
					'post_id'   => (int) $ranked_row['post_id'],
					'title'     => (string) ( $ranked_row['title'] ?? '' ),
					'post_type' => (string) ( $ranked_row['post_type'] ?? '' ),
					'url'       => $url,
					'path'      => get_url_path( $url ),
				),
				$current,
				array(
					'previous'       => $prior,
					'change_percent' => is_array( $prior ) ? percent_changes( $current, $prior, self::METRICS ) : null,
				)
			);
		}

		return $shaped;
	}

	/**
	 * The metric values of a database row as integers.
	 *
	 * @since 0.1.0
	 *
	 * @param array<string, mixed> $row Database row.
	 * @return array<string, int>
	 */
	private function get_metric_values( array $row ): array {
		$values = array();

		foreach ( self::METRICS as $metric ) {
			$values[ $metric ] = (int) ( $row[ $metric ] ?? 0 );
		}

		return $values;
	}
}
