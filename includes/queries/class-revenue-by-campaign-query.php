<?php
/**
 * Query: WooCommerce revenue by UTM campaign. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.4.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Orders and sales grouped by the campaign, or one UTM parameter, of the session they were placed in.
 *
 * @since 0.4.0
 */
class Revenue_By_Campaign_Query extends Dimension_Revenue_Query {

	/**
	 * Query name.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'revenue-by-campaign';
	}

	/**
	 * Label.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Revenue by campaign', 'ability label; shop sales grouped by marketing campaign', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'WooCommerce orders and sales from UTM-tagged campaign traffic for a date range, from Independent Analytics Pro. dimension chooses whole campaigns or one UTM parameter, as in the campaigns query. Each row has orders, gross sales, refunds, net sales, conversion rate, earnings per visitor and average order value, plus visitors and sessions. Money is in the store currency (meta.currency) in major units. Only campaigns with at least one order are listed, ranked by net sales. Paged.', 'iawp-query-helper' );
	}
}
