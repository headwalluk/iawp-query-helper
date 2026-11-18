<?php
/**
 * Base class for form submissions by a session attribute (referrer, campaign).
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Submissions grouped the way a breakdown query groups traffic, with that query's labels and filters.
 *
 * @since 0.5.0
 */
abstract class Dimension_Form_Breakdown_Query extends Form_Breakdown_Query {

	/**
	 * The breakdown this query follows.
	 *
	 * @since 0.5.0
	 * @var Dimension_Query
	 */
	protected Dimension_Query $source;

	/**
	 * Create the query.
	 *
	 * @since 0.5.0
	 *
	 * @param Environment     $environment IAWP environment.
	 * @param Query_Runner    $runner      SQL runner.
	 * @param Dimension_Query $source      Breakdown query to follow.
	 */
	public function __construct( Environment $environment, Query_Runner $runner, Dimension_Query $source ) {
		parent::__construct( $environment, $runner );
		$this->source = $source;
	}

	/**
	 * Form filter, plus the breakdown's own filters.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		$source_properties = $this->source->get_query_properties();
		unset( $source_properties['orderby'] );

		return array_merge( parent::get_specific_properties(), $source_properties );
	}

	/**
	 * Group key, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_group_column( Query_Context $context ): string {
		return $this->source->get_group_column( $context );
	}

	/**
	 * Joins, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		return $this->source->get_join_sql( $context );
	}

	/**
	 * Filters, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	protected function get_filter( Query_Context $context ): array {
		return $this->source->get_filter( $context );
	}

	/**
	 * Traffic, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Groups.
	 * @return array<string|int, array<string, mixed>>|\WP_Error
	 */
	protected function get_traffic( Query_Context $context, Date_Range $range, array $group_keys ): array|\WP_Error {
		return $this->source->get_groups( $context, $range, $group_keys, count( $group_keys ), 0 );
	}

	/**
	 * Labels, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @param Query_Context        $context     Validated input.
	 * @return array<string, mixed>
	 */
	protected function shape_labels( array $traffic_row, Query_Context $context ): array {
		return $this->source->shape_labels( $traffic_row, $context );
	}

	/**
	 * Label schema, from the breakdown.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		return $this->source->get_label_properties();
	}
}
