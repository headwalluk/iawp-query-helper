<?php
/**
 * Query: one page in detail.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * One page's figures and its traffic over time, found by post ID or URL.
 *
 * @since 0.3.0
 */
class Page_Stats_Query extends Resource_Query {

	/**
	 * Series builder.
	 *
	 * @since 0.3.0
	 * @var Traffic_Over_Time_Query
	 */
	private Traffic_Over_Time_Query $series_query;

	/**
	 * Create the query.
	 *
	 * @since 0.3.0
	 *
	 * @param Environment             $environment  IAWP environment.
	 * @param Query_Runner            $runner       SQL runner.
	 * @param Traffic_Over_Time_Query $series_query Series builder.
	 */
	public function __construct( Environment $environment, Query_Runner $runner, Traffic_Over_Time_Query $series_query ) {
		parent::__construct( $environment, $runner );
		$this->series_query = $series_query;
	}

	/**
	 * Query name.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'page-stats';
	}

	/**
	 * Label.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Page stats', 'ability label; traffic figures for one web page', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'One page in detail, for a date range, from Independent Analytics. Give post_id, or the page\'s absolute url (for example from Google Search Console). Returns one row with the same fields as top-pages, and series: views, visitors and sessions for that page per day, week or month (interval). With compare, the row carries previous figures and previous_series holds the comparison series. Errors with iawpqh_unknown_page if Independent Analytics has never recorded the page.', 'iawp-query-helper' );
	}

	/**
	 * Not paged: one page.
	 *
	 * @since 0.3.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return false;
	}

	/**
	 * Page identifier and series interval.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'post_id'  => array(
				'type'        => 'integer',
				'minimum'     => 1,
				'description' => __( 'The post, page or product ID. Give this or url.', 'iawp-query-helper' ),
			),
			'url'      => array(
				'type'        => 'string',
				'format'      => 'uri',
				'description' => __( 'The page\'s absolute URL, as Independent Analytics recorded it. Give this or post_id.', 'iawp-query-helper' ),
			),
			'interval' => array(
				'type'    => 'string',
				'enum'    => array( INTERVAL_DAILY, INTERVAL_WEEKLY, INTERVAL_MONTHLY ),
				'default' => INTERVAL_DAILY,
			),
		);
	}

	/**
	 * Output schema: the page row plus its series.
	 *
	 * @since 0.3.0
	 *
	 * @return array<string, mixed>
	 */
	public function get_output_schema(): array {
		$schema = parent::get_output_schema();

		$series_schema = array(
			'type'  => array( 'array', 'null' ),
			'items' => array(
				'type'       => 'object',
				'properties' => array_merge(
					array( 'period_start' => array( 'type' => 'string' ) ),
					$this->get_integer_properties( array( METRIC_VIEWS, METRIC_VISITORS, METRIC_SESSIONS ) )
				),
			),
		);

		$schema['properties']['series']          = $series_schema;
		$schema['properties']['previous_series'] = $series_schema;

		return $schema;
	}

	/**
	 * Find the page, then fetch its figures and series for the range and any comparison.
	 *
	 * @since 0.3.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$resource_id = $this->find_resource( $context );
		$result      = $resource_id;

		if ( is_int( $resource_id ) ) {
			$interval   = (string) $context->get_param( 'interval', INTERVAL_DAILY );
			$comparison = $context->get_comparison();
			$current    = $this->get_resources( $context, $context->get_range(), array( $resource_id ), 1, 0 );

			// A page with no traffic in the range still gets a row, with its labels and zeros.
			if ( ! is_wp_error( $current ) && empty( $current ) ) {
				$current = $this->get_label_row( $resource_id );
			}

			$page     = is_wp_error( $current ) ? $current : $this->add_comparison( $context, $current, false );
			$series   = is_wp_error( $page ) ? $page : $this->series_query->get_series( $context->get_range(), $interval, $resource_id );
			$previous = null;

			if ( ! is_wp_error( $series ) && $comparison instanceof Date_Range ) {
				$previous = $this->series_query->get_series( $comparison, $interval, $resource_id );
			}

			if ( is_wp_error( $series ) ) {
				$result = $series;
			} elseif ( is_wp_error( $previous ) ) {
				$result = $previous;
			} else {
				$result = array(
					'rows'            => $page['rows'],
					'series'          => $series,
					'previous_series' => $previous,
				);
			}
		}

		return $result;
	}

	/**
	 * The IAWP resource ID for the requested post or URL.
	 *
	 * @since 0.3.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return int|\WP_Error
	 */
	private function find_resource( Query_Context $context ): int|\WP_Error {
		global $wpdb;

		$post_id         = (int) $context->get_param( 'post_id', 0 );
		$url             = (string) $context->get_param( 'url', '' );
		$resources_table = $this->environment->get_table( IAWP_TABLE_RESOURCES );

		if ( ( $post_id > 0 ) === ( '' !== $url ) ) {
			$result = new \WP_Error( ERROR_INVALID_PARAM, __( 'Give exactly one of post_id or url.', 'iawp-query-helper' ), array( 'status' => 400 ) );
		} else {
			// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
			if ( $post_id > 0 ) {
				$sql = $wpdb->prepare( "SELECT MIN(id) AS resource_id FROM {$resources_table} WHERE resource = 'singular' AND singular_id = %d", $post_id );
			} else {
				$sql = $wpdb->prepare( "SELECT MIN(id) AS resource_id FROM {$resources_table} WHERE cached_url = %s", $url );
			}
			// phpcs:enable

			$rows = $this->runner->get_rows( $sql );

			if ( is_wp_error( $rows ) ) {
				$result = $rows;
			} elseif ( empty( $rows[0]['resource_id'] ) ) {
				$result = new \WP_Error( ERROR_UNKNOWN_PAGE, __( 'Independent Analytics has no record of that page.', 'iawp-query-helper' ), array( 'status' => 404 ) );
			} else {
				$result = (int) $rows[0]['resource_id'];
			}
		}

		return $result;
	}

	/**
	 * A row holding just a resource's labels, for a page with no traffic in the range.
	 *
	 * @since 0.3.0
	 *
	 * @param int $resource_id IAWP resource ID.
	 * @return array<int, array<string, mixed>>|\WP_Error
	 */
	private function get_label_row( int $resource_id ): array|\WP_Error {
		global $wpdb;

		$resources_table = $this->environment->get_table( IAWP_TABLE_RESOURCES );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Table name from $wpdb->prefix.
		$rows = $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT id AS resource_id, resource AS resource_type, cached_title AS title, cached_url AS url, singular_id AS post_id, cached_type AS post_type, search_query, not_found_url FROM {$resources_table} WHERE id = %d",
				$resource_id
			)
		);
		// phpcs:enable

		return is_wp_error( $rows ) ? $rows : array( $resource_id => $rows[0] ?? array( 'resource_id' => $resource_id ) );
	}
}
