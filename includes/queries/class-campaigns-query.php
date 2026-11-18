<?php
/**
 * Query: traffic by UTM campaign.
 *
 * @package IAWP_Query_Helper
 * @since 0.2.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Sessions grouped by campaign or by one UTM parameter, as on the dashboard's Campaigns tables.
 *
 * @since 0.2.0
 */
class Campaigns_Query extends Dimension_Query {

	/**
	 * Group column per dimension.
	 *
	 * @since 0.2.0
	 * @var array<string, string>
	 */
	private const GROUP_COLUMNS = array(
		'campaign'     => 'sessions.campaign_id',
		'utm_source'   => 'campaigns.utm_source_id',
		'utm_medium'   => 'campaigns.utm_medium_id',
		'utm_campaign' => 'campaigns.utm_campaign_id',
		'landing_page' => 'campaigns.landing_page_id',
	);

	/**
	 * Label columns per dimension.
	 *
	 * @since 0.2.0
	 * @var array<string, array<string, string>>
	 */
	private const LABEL_COLUMNS = array(
		'campaign'     => array(
			'landing_page' => 'landing_pages.title',
			'utm_source'   => 'utm_sources.utm_source',
			'utm_medium'   => 'utm_mediums.utm_medium',
			'utm_campaign' => 'utm_campaigns.utm_campaign',
			'utm_term'     => 'campaigns.utm_term',
			'utm_content'  => 'campaigns.utm_content',
		),
		'utm_source'   => array( 'utm_source' => 'utm_sources.utm_source' ),
		'utm_medium'   => array( 'utm_medium' => 'utm_mediums.utm_medium' ),
		'utm_campaign' => array( 'utm_campaign' => 'utm_campaigns.utm_campaign' ),
		'landing_page' => array( 'landing_page' => 'landing_pages.title' ),
	);

	/**
	 * Lookup table alias that must exist for each single-parameter dimension, as on the dashboard.
	 *
	 * @since 0.2.0
	 * @var array<string, string>
	 */
	private const REQUIRED_LOOKUPS = array(
		'utm_source'   => 'utm_sources.id',
		'utm_medium'   => 'utm_mediums.id',
		'utm_campaign' => 'utm_campaigns.id',
		'landing_page' => 'landing_pages.id',
	);

	/**
	 * Query name.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'campaigns';
	}

	/**
	 * Label.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Campaigns', 'ability label; marketing campaigns tracked with UTM parameters', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.2.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Traffic from UTM-tagged campaign links for a date range, from Independent Analytics. dimension=campaign gives one row per distinct campaign (landing page, source, medium, campaign, term, content); utm_source, utm_medium, utm_campaign or landing_page group by that one parameter. Each row has views, visitors, sessions, bounce rate, views per session and average session length, matching the dashboard\'s Campaigns tables. Untagged traffic is not included. Paged.', 'iawp-query-helper' );
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
					'enum'        => array_keys( self::GROUP_COLUMNS ),
					'default'     => 'campaign',
					'description' => __( 'Group by whole campaign, or by one UTM parameter.', 'iawp-query-helper' ),
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
		return self::GROUP_COLUMNS[ $this->get_dimension( $context ) ];
	}

	/**
	 * Joins from sessions to campaigns and every UTM lookup.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		$campaigns_table     = $this->environment->get_table( IAWP_TABLE_CAMPAIGNS );
		$landing_pages_table = $this->environment->get_table( IAWP_TABLE_LANDING_PAGES );
		$sources_table       = $this->environment->get_table( IAWP_TABLE_UTM_SOURCES );
		$mediums_table       = $this->environment->get_table( IAWP_TABLE_UTM_MEDIUMS );
		$utm_campaigns_table = $this->environment->get_table( IAWP_TABLE_UTM_CAMPAIGNS );

		return "LEFT JOIN {$campaigns_table} AS campaigns ON campaigns.campaign_id = sessions.campaign_id
			LEFT JOIN {$landing_pages_table} AS landing_pages ON landing_pages.id = campaigns.landing_page_id
			LEFT JOIN {$sources_table} AS utm_sources ON utm_sources.id = campaigns.utm_source_id
			LEFT JOIN {$mediums_table} AS utm_mediums ON utm_mediums.id = campaigns.utm_medium_id
			LEFT JOIN {$utm_campaigns_table} AS utm_campaigns ON utm_campaigns.id = campaigns.utm_campaign_id";
	}

	/**
	 * Label columns for the chosen dimension.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array<string, string>
	 */
	protected function get_label_columns( Query_Context $context ): array {
		return self::LABEL_COLUMNS[ $this->get_dimension( $context ) ];
	}

	/**
	 * Label schema: every field any dimension can return.
	 *
	 * @since 0.2.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		$properties = array();

		foreach ( array_keys( self::LABEL_COLUMNS['campaign'] ) as $field ) {
			$properties[ $field ] = array( 'type' => array( 'string', 'null' ) );
		}

		$properties['landing_page']['description'] = __( 'Title of the page the campaign link pointed to.', 'iawp-query-helper' );

		return $properties;
	}

	/**
	 * For a single-parameter dimension, skip campaigns whose lookup row is missing.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	protected function get_filter( Query_Context $context ): array {
		$dimension = $this->get_dimension( $context );
		$result    = array( '', array() );

		if ( isset( self::REQUIRED_LOOKUPS[ $dimension ] ) ) {
			$result = array( 'AND ' . self::REQUIRED_LOOKUPS[ $dimension ] . ' IS NOT NULL', array() );
		}

		return $result;
	}

	/**
	 * The requested dimension.
	 *
	 * @since 0.2.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	private function get_dimension( Query_Context $context ): string {
		$dimension = (string) $context->get_param( 'dimension', 'campaign' );

		return isset( self::GROUP_COLUMNS[ $dimension ] ) ? $dimension : 'campaign';
	}
}
