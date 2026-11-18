<?php
/**
 * Query: traffic by country.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by visitor country, as on the dashboard's Geographic table.
 *
 * @since 0.2.0
 */
class Countries_Query extends Dimension_Query {

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'countries';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Countries', 'ability label; visitor locations by country', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic by visitor country for a date range, from Independent Analytics: country code, country name and continent, with views, visitors, sessions, bounce rate, views per session and average session length. Matches the dashboard\'s countries table. Sessions with no known location are left out. Visitors must not be summed across rows. Paged.', 'iawp-query-helper' );
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
		return 'sessions.country_id';
	}

	/**
	 * Join to countries.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	public function get_join_sql( Query_Context $context ): string {
		$countries_table = $this->environment->get_table( IAWP_TABLE_COUNTRIES );

		return "LEFT JOIN {$countries_table} AS countries ON countries.country_id = sessions.country_id";
	}

	/**
	 * Ties are broken by country name, as on the dashboard.
	 *
	 * @since 0.5.1
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	public function get_tie_breaker( Query_Context $context ): string {
		return 'country';
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
			'country_code' => 'countries.country_code',
			'country'      => 'countries.country',
			'continent'    => 'countries.continent',
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
			'country_code' => array(
				'type'        => array( 'string', 'null' ),
				'description' => __( 'ISO 3166-1 alpha-2 code.', 'iawp-query-helper' ),
			),
			'country'      => array( 'type' => array( 'string', 'null' ) ),
			'continent'    => array( 'type' => array( 'string', 'null' ) ),
		);
	}
}
