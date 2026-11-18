<?php
/**
 * Query: site-wide WooCommerce figures for a range. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.4.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * The ecommerce figures at the top of the IAWP dashboard.
 *
 * @since 0.4.0
 */
class Ecommerce_Summary_Query extends Query {

	/**
	 * Metrics reported in `previous` and `change_percent`.
	 *
	 * @since 0.4.0
	 * @var string[]
	 */
	private const METRICS = array( 'visitors', 'orders', 'gross_sales', 'refunds', 'refunded_amount', 'net_sales', 'conversion_rate_percent', 'earnings_per_visitor', 'average_order_value' );

	/**
	 * Query name.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'ecommerce-summary';
	}

	/**
	 * Label.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Ecommerce summary', 'ability label; shop sales totals', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.4.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Site-wide WooCommerce totals for a date range, from Independent Analytics Pro: orders, gross sales, refunds, refunded amount, net sales, conversion rate (orders per visitor), earnings per visitor and average order value, with visitors. Money is in the store currency (the currency field) in major units. Matches the ecommerce figures on the dashboard. Use compare=previous_year with period=year_to_date for year-on-year. Returns one row.', 'iawp-query-helper' );
	}

	/**
	 * Only with a licensed copy of IAWP Pro.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_pro_only(): bool {
		return true;
	}

	/**
	 * Row schema.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, mixed>
	 */
	protected function get_row_schema(): array {
		$metric_properties = array_merge(
			$this->get_integer_properties( array( 'visitors', 'orders', 'refunds' ) ),
			array(
				'gross_sales'             => array( 'type' => 'number' ),
				'refunded_amount'         => array( 'type' => 'number' ),
				'net_sales'               => array( 'type' => 'number' ),
				'conversion_rate_percent' => array( 'type' => 'number' ),
				'earnings_per_visitor'    => array( 'type' => 'number' ),
				'average_order_value'     => array( 'type' => 'number' ),
			)
		);

		return array(
			'type'       => 'object',
			'properties' => array_merge(
				array(
					'currency' => array(
						'type'        => array( 'string', 'null' ),
						'description' => __( 'Store base currency, ISO 4217.', 'iawp-query-helper' ),
					),
				),
				$metric_properties,
				$this->get_comparison_properties( $metric_properties )
			),
		);
	}

	/**
	 * Run the summary for the range, and the comparison range if requested.
	 *
	 * @since 0.4.0
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
			$row = array_merge(
				array( 'currency' => function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : null ),
				$current,
				array(
					'previous'       => $previous,
					'change_percent' => is_array( $previous ) ? percent_changes( $current, $previous, self::METRICS ) : null,
				)
			);

			$result = array( 'rows' => array( $row ) );
		}

		return $result;
	}

	/**
	 * Visitors and order totals for one range, as the dashboard counts them.
	 *
	 * @since 0.4.0
	 *
	 * @param Date_Range $range Range.
	 * @return array<string, int|float>|\WP_Error
	 */
	private function get_summary( Date_Range $range ): array|\WP_Error {
		global $wpdb;

		$orders_table   = $this->environment->get_table( IAWP_TABLE_ORDERS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$visitors       = $this->get_site_visitors( $range );
		$range_values   = array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() );

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix.
		$order_rows = is_wp_error( $visitors ) ? $visitors : $this->runner->get_rows(
			$wpdb->prepare(
				"SELECT COUNT(*) AS orders,
					CAST(IFNULL(SUM(orders.total), 0) AS SIGNED) AS gross_sales,
					CAST(IFNULL(SUM(orders.total_refunded), 0) AS SIGNED) AS refunded_amount,
					CAST(IFNULL(SUM(orders.total_refunds), 0) AS SIGNED) AS refunds,
					CAST(IFNULL(SUM(orders.total - orders.total_refunded), 0) AS SIGNED) AS net_sales
				FROM {$orders_table} AS orders
				INNER JOIN {$views_table} AS views ON views.id = orders.initial_view_id
				INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
				WHERE orders.is_included_in_analytics = 1
					AND sessions.created_at BETWEEN %s AND %s
					AND views.viewed_at BETWEEN %s AND %s",
				$range_values
			)
		);
		// phpcs:enable

		if ( is_wp_error( $order_rows ) ) {
			$result = $order_rows;
		} else {
			$result = array_merge( array( 'visitors' => $visitors ), get_order_metrics( $order_rows[0] ?? array(), $visitors, true ) );
		}

		return $result;
	}
}
