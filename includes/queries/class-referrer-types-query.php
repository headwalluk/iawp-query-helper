<?php
/**
 * Query: traffic by referrer type (Search, Social, AI, Direct...).
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by referrer type, as on the dashboard's Referrer Types table.
 *
 * @since 0.2.0
 */
class Referrer_Types_Query extends Dimension_Query {

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'referrer-types';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Referrer types', 'ability label; categories of traffic source such as search or social', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic split by source category for a date range, from Independent Analytics: Search, Social, AI, Direct, Referrer (other websites) and Ad, each with views, visitors, sessions, bounce rate, views per session and average session length. Matches the dashboard\'s Referrer Types table. Use it for "how much traffic comes from search?" questions; use referrers for individual sites.', 'iawp-query-helper' );
	}

	/**
	 * Group key.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_group_column( Query_Context $context ): string {
		return 'referrers.referrer_type_id';
	}

	/**
	 * Joins to the referrer tables; sessions without a referrer drop out, as on the dashboard.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		$referrers_table      = $this->environment->get_table( IAWP_TABLE_REFERRERS );
		$referrer_types_table = $this->environment->get_table( IAWP_TABLE_REFERRER_TYPES );

		return "INNER JOIN {$referrers_table} AS referrers ON referrers.id = sessions.referrer_id
			INNER JOIN {$referrer_types_table} AS referrer_types ON referrer_types.id = referrers.referrer_type_id";
	}

	/**
	 * Label columns.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, string>
	 */
	protected function get_label_columns( Query_Context $context ): array {
		return array( 'referrer_type' => 'referrer_types.referrer_type' );
	}

	/**
	 * Label schema.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		return array( 'referrer_type' => array( 'type' => array( 'string', 'null' ) ) );
	}
}
