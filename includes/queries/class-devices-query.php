<?php
/**
 * Query: traffic by device type, operating system or browser.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by one device attribute, as on the dashboard's Devices tables.
 *
 * @since 0.2.0
 */
class Devices_Query extends Dimension_Query {

	/**
	 * Per dimension: group column, lookup table constant, lookup key column, label column.
	 *
	 * @since 0.2.0
	 * @var array<string, array{0: string, 1: string, 2: string, 3: string}>
	 */
	private const DIMENSIONS = array(
		'device_type' => array( 'sessions.device_type_id', IAWP_TABLE_DEVICE_TYPES, 'device_type_id', 'device_type' ),
		'os'          => array( 'sessions.device_os_id', IAWP_TABLE_DEVICE_OSS, 'device_os_id', 'device_os' ),
		'browser'     => array( 'sessions.device_browser_id', IAWP_TABLE_DEVICE_BROWSERS, 'device_browser_id', 'device_browser' ),
	);

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'devices';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Devices', 'ability label; visitor device type, operating system or browser', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic by device for a date range, from Independent Analytics. dimension=device_type gives Desktop, Mobile and Tablet; os gives operating systems; browser gives browsers. Each row has views, visitors, sessions, bounce rate, views per session and average session length, matching the dashboard\'s Devices tables. Visitors must not be summed across rows. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Sort order and dimension.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_specific_properties(): array {
		return array_merge(
			parent::get_specific_properties(),
			array(
				'dimension' => array(
					'type'        => 'string',
					'enum'        => array_keys( self::DIMENSIONS ),
					'default'     => 'device_type',
					'description' => __( 'Which device attribute to group by.', 'iawp-query-helper' ),
				),
			)
		);
	}

	/**
	 * Group key for the chosen dimension.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_group_column( Query_Context $context ): string {
		return $this->get_dimension( $context )[0];
	}

	/**
	 * Join to the chosen dimension's lookup table.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		list( $group_column, $table_name, $key_column ) = $this->get_dimension( $context );

		$lookup_table = $this->environment->get_table( $table_name );

		return "LEFT JOIN {$lookup_table} AS lookup ON lookup.{$key_column} = {$group_column}";
	}

	/**
	 * Label column: always `device`.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, string>
	 */
	protected function get_label_columns( Query_Context $context ): array {
		return array( 'device' => 'lookup.' . $this->get_dimension( $context )[3] );
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
			'device' => array(
				'type'        => array( 'string', 'null' ),
				'description' => __( 'Device type, operating system or browser, depending on dimension.', 'iawp-query-helper' ),
			),
		);
	}

	/**
	 * The definition of the requested dimension.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: string, 2: string, 3: string}
	 */
	private function get_dimension( Query_Context $context ): array {
		$dimension = (string) $context->get_param( 'dimension', 'device_type' );

		return self::DIMENSIONS[ $dimension ] ?? self::DIMENSIONS['device_type'];
	}
}
