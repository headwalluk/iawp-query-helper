<?php
/**
 * Query: WooCommerce revenue by landing page. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.4.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Orders and sales grouped by the page their session started on, as on the dashboard's Pages table.
 *
 * @since 0.4.0
 */
class Revenue_By_Landing_Page_Query extends Revenue_Query {

	/**
	 * The page query that supplies traffic and labels.
	 *
	 * @since 0.4.0
	 * @var Resource_Query
	 */
	private Resource_Query $source;

	/**
	 * Create the query.
	 *
	 * @since 0.4.0
	 *
	 * @param Environment    $environment IAWP environment.
	 * @param Query_Runner   $runner      SQL runner.
	 * @param Resource_Query $source      Page query for traffic and labels.
	 */
	public function __construct( Environment $environment, Query_Runner $runner, Resource_Query $source ) {
		parent::__construct( $environment, $runner );
		$this->source = $source;
	}

	/**
	 * Query name.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'revenue-by-landing-page';
	}

	/**
	 * Label.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Revenue by landing page', 'ability label; shop sales grouped by the page where the visit started', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'WooCommerce orders and sales by landing page for a date range, from Independent Analytics Pro: the page each ordering visit started on, with its title, URL and kind, plus orders, gross sales, refunds, net sales, conversion rate, earnings per visitor and average order value, and the page\'s visitors and sessions. Money is in the store currency (meta.currency) in major units. Matches the ecommerce columns of the dashboard\'s Pages table. Pair with Google Search Console landing pages to see which search traffic earns money. Only pages with at least one order are listed, ranked by net sales. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Orders group by the resource of the session's first view.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_group_column( Query_Context $context ): string {
		return 'views.resource_id';
	}

	/**
	 * No extra joins.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		return '';
	}

	/**
	 * No extra filters.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	protected function get_filter( Query_Context $context ): array {
		return array( '', array() );
	}

	/**
	 * Page traffic, from the page query.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Resource IDs.
	 * @return array<string|int, array<string, mixed>>|\WP_Error
	 */
	protected function get_traffic( Query_Context $context, Date_Range $range, array $group_keys ): array|\WP_Error {
		return $this->source->get_resources( $context, $range, array_map( 'intval', $group_keys ), count( $group_keys ), 0 );
	}

	/**
	 * Page labels.
	 *
	 * @since 0.4.0
	 *
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @param Query_Context        $context     Validated input.
	 * @return array<string, mixed>
	 */
	protected function shape_labels( array $traffic_row, Query_Context $context ): array {
		return array_intersect_key( $this->source->shape_labels( $traffic_row ), $this->get_label_properties() );
	}

	/**
	 * Label schema.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		return array(
			'resource_type' => array( 'type' => 'string' ),
			'title'         => array( 'type' => array( 'string', 'null' ) ),
			'url'           => array( 'type' => array( 'string', 'null' ) ),
			'path'          => array( 'type' => array( 'string', 'null' ) ),
			'post_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'post_type'     => array( 'type' => array( 'string', 'null' ) ),
		);
	}
}
