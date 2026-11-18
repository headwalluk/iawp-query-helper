<?php
/**
 * Query: what this site offers, for an agent to read first.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Site settings, limits and the list of queries available on this site.
 *
 * @since 0.2.0
 */
class Catalogue_Query extends Query {

	/**
	 * Query library, to list the queries.
	 *
	 * @since 0.2.0
	 * @var Query_Registry
	 */
	private Query_Registry $registry;

	/**
	 * Create the query.
	 *
	 * @since 0.2.0
	 *
	 * @param Environment    $environment IAWP environment.
	 * @param Query_Runner   $runner      SQL runner.
	 * @param Query_Registry $registry    Query library.
	 */
	public function __construct( Environment $environment, Query_Runner $runner, Query_Registry $registry ) {
		parent::__construct( $environment, $runner );
		$this->registry = $registry;
	}

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'catalogue';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Analytics catalogue', 'ability label; list of available analytics queries and site settings', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Call this first. Describes the Independent Analytics data on this site: timezone, week start, store currency, the first day with data, whether Independent Analytics Pro is active, the limits on date ranges and page sizes, and every analytics query available with what it is for. Takes no parameters.', 'iawp-query-helper' );
	}

	/**
	 * No date range.
	 *
	 * @since 0.2.0
	 *
	 * @return bool
	 */
	public function uses_date_range(): bool {
		return false;
	}

	/**
	 * Takes no parameters.
	 *
	 * @since 0.2.0
	 *
	 * @param string[] $periods Accepted period presets (unused).
	 * @return array<string, mixed>
	 */
	public function get_input_schema( array $periods ): array {
		return array(
			'type'                 => 'object',
			'properties'           => array(),
			'additionalProperties' => false,
			'default'              => array(),
		);
	}

	/**
	 * Row schema.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		return array(
			'type'       => 'object',
			'properties' => array(
				'site_url'        => array( 'type' => 'string' ),
				'timezone'        => array( 'type' => 'string' ),
				'utc_offset'      => array( 'type' => 'string' ),
				'week_starts_on'  => array( 'type' => 'string' ),
				'currency'        => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'Store currency, when WooCommerce is active.', 'iawp-query-helper' ),
				),
				'first_view_date' => array(
					'type'        => array( 'string', 'null' ),
					'description' => __( 'First day with recorded views, Y-m-d, site timezone. Ranges before it return zeros.', 'iawp-query-helper' ),
				),
				'is_pro'          => array( 'type' => 'boolean' ),
				'limits'          => array( 'type' => 'object' ),
				'periods'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'string' ),
				),
				'queries'         => array(
					'type'  => 'array',
					'items' => array( 'type' => 'object' ),
				),
			),
		);
	}

	/**
	 * Describe the site and its queries.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$first_view = $this->get_first_view_date();

		if ( is_wp_error( $first_view ) ) {
			$result = $first_view;
		} else {
			$is_pro    = $this->environment->is_pro();
			$week_days = array( 'Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday' );
			$queries   = array();

			foreach ( $this->registry->get_all() as $query ) {
				$queries[] = array(
					'name'         => $query->get_name(),
					'ability'      => ABILITY_NAMESPACE . '/' . $query->get_name(),
					'rest_route'   => '/' . REST_NAMESPACE . '/' . $query->get_name(),
					'label'        => $query->get_label(),
					'description'  => $query->get_description(),
					'is_paged'     => $query->is_paged(),
					'is_pro_only'  => $query->is_pro_only(),
					'is_available' => $is_pro || ! $query->is_pro_only(),
				);
			}

			$result = array(
				'rows' => array(
					array(
						'site_url'        => home_url( '/' ),
						'timezone'        => wp_timezone_string(),
						'utc_offset'      => $this->environment->get_site_offset(),
						'week_starts_on'  => $week_days[ $this->environment->get_week_start_day() ],
						'currency'        => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : null,
						'first_view_date' => $first_view,
						'is_pro'          => $is_pro,
						'limits'          => array(
							'max_range_days'            => get_filtered_int( FILTER_MAX_RANGE_DAYS, DEFAULT_MAX_RANGE_DAYS, MIN_MAX_RANGE_DAYS, DEFAULT_MAX_RANGE_DAYS ),
							'max_per_page'              => MAX_PER_PAGE,
							'max_row_offset'            => MAX_GROUPED_ROW_OFFSET,
							'statement_timeout_seconds' => $this->runner->get_timeout_seconds(),
							'concurrent_queries'        => $this->runner->get_slot_count(),
						),
						'periods'         => Plugin::instance()->get_resolver()->get_periods(),
						'queries'         => $queries,
					),
				),
			);
		}

		return $result;
	}

	/**
	 * First day with a recorded view, in the site timezone, or null when there are none.
	 *
	 * @since 0.2.0
	 *
	 * @return string|null|\WP_Error
	 */
	private function get_first_view_date(): string|null|\WP_Error {
		$views_table = $this->environment->get_table( IAWP_TABLE_VIEWS );

		// MIN() on an indexed column reads one index entry.
		$rows = $this->runner->get_rows( "SELECT MIN(viewed_at) AS first_viewed_at FROM {$views_table}" );

		if ( is_wp_error( $rows ) ) {
			$result = $rows;
		} elseif ( empty( $rows[0]['first_viewed_at'] ) ) {
			$result = null;
		} else {
			$first_viewed = new \DateTimeImmutable( (string) $rows[0]['first_viewed_at'], new \DateTimeZone( 'UTC' ) );
			$result       = $first_viewed->setTimezone( wp_timezone() )->format( DATE_FORMAT );
		}

		return $result;
	}
}
