<?php
/**
 * Query: traffic by city.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by visitor city, as on the dashboard's cities table.
 *
 * @since 0.2.0
 */
class Cities_Query extends Dimension_Query {

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'cities';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Cities', 'ability label; visitor locations by city', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic by visitor city for a date range, from Independent Analytics: city, region, country code and country, with views, visitors, sessions, bounce rate, views per session and average session length. Matches the dashboard\'s cities table. Filter with country_code to see one country. Visitors must not be summed across rows. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Sort order and country filter.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array_merge(
			parent::get_specific_properties(),
			array(
				'country_code' => array(
					'type'        => 'string',
					'pattern'     => '^[A-Za-z]{2}$',
					'description' => __( 'Only cities in this country, by ISO 3166-1 alpha-2 code, such as GB.', 'iawp-query-helper' ),
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
	protected function get_group_column( Query_Context $context ): string {
		return 'sessions.city_id';
	}

	/**
	 * Joins to cities and their countries.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		$cities_table    = $this->environment->get_table( IAWP_TABLE_CITIES );
		$countries_table = $this->environment->get_table( IAWP_TABLE_COUNTRIES );

		return "LEFT JOIN {$cities_table} AS cities ON cities.city_id = sessions.city_id
			LEFT JOIN {$countries_table} AS countries ON countries.country_id = cities.country_id";
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
		return array(
			'city'         => 'cities.city',
			'subdivision'  => 'cities.subdivision',
			'country_code' => 'countries.country_code',
			'country'      => 'countries.country',
		);
	}

	/**
	 * Label schema.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		return array(
			'city'         => array( 'type' => array( 'string', 'null' ) ),
			'subdivision'  => array(
				'type'        => array( 'string', 'null' ),
				'description' => __( 'Region, state or county.', 'iawp-query-helper' ),
			),
			'country_code' => array( 'type' => array( 'string', 'null' ) ),
			'country'      => array( 'type' => array( 'string', 'null' ) ),
		);
	}

	/**
	 * Country filter.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	protected function get_filter( Query_Context $context ): array {
		$country_code = strtoupper( (string) $context->get_param( 'country_code', '' ) );
		$result       = array( '', array() );

		if ( '' !== $country_code ) {
			$result = array( 'AND countries.country_code = %s', array( $country_code ) );
		}

		return $result;
	}
}
