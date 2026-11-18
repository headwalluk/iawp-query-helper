<?php
/**
 * Query: WooCommerce revenue by referrer. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.4.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Orders and sales grouped by the referrer of the session they were placed in.
 *
 * @since 0.4.0
 */
class Revenue_By_Referrer_Query extends Dimension_Revenue_Query {

	/**
	 * Query name.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'revenue-by-referrer';
	}

	/**
	 * Label.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Revenue by referrer', 'ability label; shop sales grouped by the website that sent the visitor', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'WooCommerce orders and sales by traffic source for a date range, from Independent Analytics Pro: each referrer (Google, Facebook, Direct...) with orders, gross sales, refunds, net sales, conversion rate, earnings per visitor and average order value, plus its visitors and sessions. Money is in the store currency (meta.currency) in major units. Matches the ecommerce columns of the dashboard\'s Referrers table. Only referrers with at least one order are listed, ranked by net sales. Filter with referrer_type. Paged.', 'iawp-query-helper' );
	}
}
