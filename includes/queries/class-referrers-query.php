<?php
/**
 * Query: traffic by referring site.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by referrer, as on the dashboard's Referrers table.
 *
 * @since 0.2.0
 */
class Referrers_Query extends Dimension_Query {

	/**
	 * Referrer types IAWP records.
	 *
	 * @since 0.2.0
	 * @var string[]
	 */
	private const REFERRER_TYPES = array( 'Ad', 'AI', 'Direct', 'Referrer', 'Search', 'Social' );

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'referrers';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Referrers', 'ability label; websites that sent visitors', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic by referring source for a date range, from Independent Analytics: each row is a referrer (such as Google, Facebook, an AI assistant, or Direct) with its domain and type, plus views, visitors, sessions, bounce rate, views per session and average session length. Matches the dashboard\'s Referrers table. Filter by referrer_type to see, for example, only Search or only AI. Visitors must not be summed across rows. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Sort order and referrer type filter.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array_merge(
			parent::get_specific_properties(),
			array(
				'referrer_type' => array(
					'type'        => 'string',
					'enum'        => self::REFERRER_TYPES,
					'description' => __( 'Only referrers of this type.', 'iawp-query-helper' ),
				),
			)
		);
	}

	/**
	 * Group key.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	public function get_group_column( Query_Context $context ): string {
		return 'sessions.referrer_id';
	}

	/**
	 * Joins to the referrer tables.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	public function get_join_sql( Query_Context $context ): string {
		$referrers_table      = $this->environment->get_table( IAWP_TABLE_REFERRERS );
		$referrer_types_table = $this->environment->get_table( IAWP_TABLE_REFERRER_TYPES );

		return "LEFT JOIN {$referrers_table} AS referrers ON referrers.id = sessions.referrer_id
			LEFT JOIN {$referrer_types_table} AS referrer_types ON referrer_types.id = referrers.referrer_type_id";
	}

	/**
	 * Label columns.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, string>
	 */
	public function get_label_columns( Query_Context $context ): array {
		return array(
			'referrer'      => 'referrers.referrer',
			'domain'        => 'referrers.domain',
			'referrer_type' => 'referrer_types.referrer_type',
		);
	}

	/**
	 * Label schema.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	public function get_label_properties(): array {
		return array(
			'referrer'      => array(
				'type'        => array( 'string', 'null' ),
				'description' => __( 'Referrer name, such as Google or Direct.', 'iawp-query-helper' ),
			),
			'domain'        => array(
				'type'        => array( 'string', 'null' ),
				'description' => __( 'Referring domain; empty for Direct.', 'iawp-query-helper' ),
			),
			'referrer_type' => array( 'type' => array( 'string', 'null' ) ),
		);
	}

	/**
	 * Referrer type filter.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	public function get_filter( Query_Context $context ): array {
		$referrer_type = (string) $context->get_param( 'referrer_type', '' );
		$result        = array( '', array() );

		if ( '' !== $referrer_type ) {
			$result = array( 'AND referrer_types.referrer_type = %s', array( $referrer_type ) );
		}

		return $result;
	}
}
