<?php
/**
 * Base class for WooCommerce revenue breakdowns. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.4.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Ranks groups (landing pages, referrers, campaigns) by order value, then adds their traffic.
 *
 * An order belongs to the session it was placed in, and counts when that session's first view
 * is in the range, as on the dashboard. Traffic and labels come from the matching traffic query.
 *
 * @since 0.4.0
 */
abstract class Revenue_Query extends Query {

	/**
	 * Order metrics, reported per group and in `previous` and `change_percent`.
	 *
	 * @since 0.4.0
	 * @var string[]
	 */
	protected const ORDER_METRICS = array( 'orders', 'gross_sales', 'refunds', 'refunded_amount', 'net_sales', 'conversion_rate_percent', 'earnings_per_visitor', 'average_order_value' );

	/**
	 * Order metrics a caller may sort by.
	 *
	 * @since 0.4.0
	 * @var string[]
	 */
	protected const SORTABLE_METRICS = array( 'net_sales', 'gross_sales', 'orders' );

	/**
	 * Group key expression, reachable from orders → views → sessions.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract protected function get_group_column( Query_Context $context ): string;

	/**
	 * JOIN clauses from `sessions` that the group key and filters need.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	abstract protected function get_join_sql( Query_Context $context ): string;

	/**
	 * Extra WHERE conditions and values.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	abstract protected function get_filter( Query_Context $context ): array;

	/**
	 * Traffic rows for the given groups, keyed by group key.
	 *
	 * @since 0.4.0
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
	 * @since 0.4.0
	 *
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @param Query_Context        $context     Validated input.
	 * @return array<string, mixed>
	 */
	abstract protected function shape_labels( array $traffic_row, Query_Context $context ): array;

	/**
	 * Schema of the label fields.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	abstract protected function get_label_properties(): array;

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
	 * Results are paged.
	 *
	 * @since 0.4.0
	 *
	 * @return bool
	 */
	public function is_paged(): bool {
		return true;
	}

	/**
	 * Sort order.
	 *
	 * @since 0.4.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array(
			'orderby' => array(
				'type'        => 'string',
				'enum'        => self::SORTABLE_METRICS,
				'default'     => 'net_sales',
				'description' => __( 'Order metric to rank by, highest first.', 'iawp-query-helper' ),
			),
		);
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
			$this->get_integer_properties( array( 'visitors', 'sessions', 'orders', 'refunds' ) ),
			array(
				'gross_sales'             => array( 'type' => 'number' ),
				'refunded_amount'         => array( 'type' => 'number' ),
				'net_sales'               => array(
					'type'        => 'number',
					'description' => __( 'Gross sales less refunds, in the store currency (meta.currency), major units.', 'iawp-query-helper' ),
				),
				'conversion_rate_percent' => array(
					'type'        => 'number',
					'description' => __( 'Orders as a percentage of visitors.', 'iawp-query-helper' ),
				),
				'earnings_per_visitor'    => array( 'type' => 'number' ),
				'average_order_value'     => array(
					'type'        => 'number',
					'description' => __( 'Gross sales divided by orders.', 'iawp-query-helper' ),
				),
			)
		);

		return array(
			'type'       => 'object',
			'properties' => array_merge( $this->get_label_properties(), $metric_properties, $this->get_comparison_properties( $metric_properties ) ),
		);
	}

	/**
	 * Rank groups by order value, add traffic, and compare when asked.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context Validated input and ranges.
	 * @return array<string, mixed>|\WP_Error
	 */
	public function run( Query_Context $context ): array|\WP_Error {
		$per_page = $context->get_per_page();
		$orders   = $this->get_order_rows( $context, $context->get_range(), array(), $per_page + 1, $context->get_offset() );
		$result   = $orders;

		if ( ! is_wp_error( $orders ) ) {
			$has_more   = count( $orders ) > $per_page;
			$orders     = array_slice( $orders, 0, $per_page, true );
			$group_keys = array_keys( $orders );
			$traffic    = empty( $group_keys ) ? array() : $this->get_traffic( $context, $context->get_range(), $group_keys );
			$comparison = $context->get_comparison();
			$previous   = array( array(), array() );

			if ( ! is_wp_error( $traffic ) && $comparison instanceof Date_Range && ! empty( $group_keys ) ) {
				$previous = $this->get_comparison_data( $context, $comparison, $group_keys );
			}

			if ( is_wp_error( $traffic ) ) {
				$result = $traffic;
			} elseif ( is_wp_error( $previous ) ) {
				$result = $previous;
			} else {
				$result = array(
					'rows'     => $this->shape_rows( $context, $orders, $traffic, $previous, $comparison instanceof Date_Range ),
					'has_more' => $has_more,
				);
			}
		}

		return $result;
	}

	/**
	 * Order totals per group in minor units, keyed by group key.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Only these groups; empty for a ranked page of all.
	 * @param int           $limit      Rows to fetch.
	 * @param int           $offset     Rows to skip.
	 * @return array<string|int, array<string, mixed>>|\WP_Error
	 */
	protected function get_order_rows( Query_Context $context, Date_Range $range, array $group_keys, int $limit, int $offset ): array|\WP_Error {
		global $wpdb;

		$orders_table   = $this->environment->get_table( IAWP_TABLE_ORDERS );
		$views_table    = $this->environment->get_table( IAWP_TABLE_VIEWS );
		$sessions_table = $this->environment->get_table( IAWP_TABLE_SESSIONS );
		$group_column   = $this->get_group_column( $context );
		$join_sql       = $this->get_join_sql( $context );
		$orderby        = in_array( $context->get_param( 'orderby' ), self::SORTABLE_METRICS, true ) ? (string) $context->get_param( 'orderby' ) : 'net_sales';

		list( $filter_sql, $filter_values ) = $this->get_filter( $context );

		$values = array_merge( array( $range->get_start_utc(), $range->get_end_utc(), $range->get_start_utc(), $range->get_end_utc() ), $filter_values );

		if ( ! empty( $group_keys ) ) {
			$filter_sql .= sprintf( ' AND %s IN (%s)', $group_column, $this->get_integer_placeholders( count( $group_keys ) ) );
			$values      = array_merge( $values, array_map( 'intval', $group_keys ) );
		}

		$values[] = $limit;
		$values[] = $offset;

		// phpcs:disable WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Values passed as one array. Table names from $wpdb->prefix; group, join and filter SQL fixed per query class; $orderby is whitelisted.
		$sql = $wpdb->prepare(
			"SELECT {$group_column} AS group_key,
				COUNT(*) AS orders,
				CAST(SUM(orders.total) AS SIGNED) AS gross_sales,
				CAST(SUM(orders.total_refunded) AS SIGNED) AS refunded_amount,
				CAST(SUM(orders.total_refunds) AS SIGNED) AS refunds,
				CAST(SUM(orders.total - orders.total_refunded) AS SIGNED) AS net_sales
			FROM {$orders_table} AS orders
			INNER JOIN {$views_table} AS views ON views.id = orders.initial_view_id
			INNER JOIN {$sessions_table} AS sessions ON sessions.session_id = views.session_id
			{$join_sql}
			WHERE orders.is_included_in_analytics = 1
				AND views.viewed_at BETWEEN %s AND %s
				AND sessions.created_at BETWEEN %s AND %s
				AND {$group_column} IS NOT NULL
				{$filter_sql}
			GROUP BY {$group_column}
			ORDER BY {$orderby} DESC, group_key ASC
			LIMIT %d OFFSET %d",
			$values
		);
		// phpcs:enable

		$rows   = $this->runner->get_rows( $sql );
		$result = $rows;

		if ( ! is_wp_error( $rows ) ) {
			$result = array();

			foreach ( $rows as $row ) {
				$result[ $row['group_key'] ] = $row;
			}
		}

		return $result;
	}

	/**
	 * Orders and traffic for the comparison range, for the same groups.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $comparison Comparison range.
	 * @param array         $group_keys Groups.
	 * @return array{0: array, 1: array}|\WP_Error Orders and traffic, each keyed by group key.
	 */
	private function get_comparison_data( Query_Context $context, Date_Range $comparison, array $group_keys ): array|\WP_Error {
		$orders  = $this->get_order_rows( $context, $comparison, $group_keys, count( $group_keys ), 0 );
		$traffic = is_wp_error( $orders ) ? $orders : $this->get_traffic( $context, $comparison, $group_keys );

		if ( is_wp_error( $traffic ) ) {
			$result = $traffic;
		} else {
			$result = array( $orders, $traffic );
		}

		return $result;
	}

	/**
	 * Shape rows: labels from traffic, then traffic and order metrics.
	 *
	 * @since 0.4.0
	 *
	 * @param Query_Context $context      Validated input.
	 * @param array         $orders       Order rows by group key.
	 * @param array         $traffic      Traffic rows by group key.
	 * @param array         $previous     Comparison orders and traffic.
	 * @param bool          $is_comparing Whether comparing.
	 * @return array<int, array<string, mixed>>
	 */
	private function shape_rows( Query_Context $context, array $orders, array $traffic, array $previous, bool $is_comparing ): array {
		$rows = array();

		foreach ( $orders as $group_key => $order_row ) {
			$traffic_row = $traffic[ $group_key ] ?? array();
			$metrics     = $this->get_metrics( $order_row, $traffic_row );
			$prior       = null;

			if ( $is_comparing ) {
				$prior = $this->get_metrics( $previous[0][ $group_key ] ?? array(), $previous[1][ $group_key ] ?? array() );
			}

			$rows[] = array_merge(
				$this->shape_labels( $traffic_row, $context ),
				$metrics,
				array(
					'previous'       => $prior,
					'change_percent' => is_array( $prior ) ? percent_changes( $metrics, $prior, array_merge( array( 'visitors', 'sessions' ), self::ORDER_METRICS ) ) : null,
				)
			);
		}

		return $rows;
	}

	/**
	 * Traffic and order metrics for one group.
	 *
	 * @since 0.4.0
	 *
	 * @param array<string, mixed> $order_row   Order row.
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @return array<string, int|float>
	 */
	private function get_metrics( array $order_row, array $traffic_row ): array {
		$visitors = (int) ( $traffic_row['visitors'] ?? 0 );

		return array_merge(
			array(
				'visitors' => $visitors,
				'sessions' => (int) ( $traffic_row['sessions'] ?? 0 ),
			),
			get_order_metrics( $order_row, $visitors )
		);
	}
}
