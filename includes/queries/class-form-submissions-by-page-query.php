<?php
/**
 * Query: form submissions by the page they were made on. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Submissions grouped by the page the form was submitted on, as on the dashboard's Pages table.
 *
 * @since 0.5.0
 */
class Form_Submissions_By_Page_Query extends Form_Breakdown_Query {

	/**
	 * The page query that supplies traffic and labels.
	 *
	 * @since 0.5.0
	 * @var Resource_Query
	 */
	private Resource_Query $source;

	/**
	 * Create the query.
	 *
	 * @since 0.5.0
	 *
	 * @param Environment    $environment IAWP environment.
	 * @param Query_Runner   $runner      SQL runner.
	 * @param Resource_Query $source      Page query for traffic and labels.
	 */
	public function __construct( Environment $environment, Query_Runner $runner, Resource_Query $source ) {
		parent::__construct( $environment, $runner );
		$this->source = $source;
	}

	/**
	 * Query name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form-submissions-by-page';
	}

	/**
	 * Label.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Form submissions by page', 'ability label; contact form entries grouped by web page', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Form submissions by page for a date range, from Independent Analytics Pro: the page each form was submitted on, with its title, URL and kind, plus submissions, conversion rate (submissions as a percentage of the page\'s visitors), and the page\'s visitors and sessions. Matches the form columns of the dashboard\'s Pages table. Filter with form_id (from the form-submissions query). Only pages with at least one submission are listed, most first. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Submissions group by the resource of the view they were made from.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_group_column( Query_Context $context ): string {
		return 'views.resource_id';
	}

	/**
	 * No extra joins.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return string
	 */
	protected function get_join_sql( Query_Context $context ): string {
		return '';
	}

	/**
	 * No extra filters.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context Validated input.
	 * @return array{0: string, 1: array<int, mixed>}
	 */
	protected function get_filter( Query_Context $context ): array {
		return array( '', array() );
	}

	/**
	 * Page traffic, from the page query.
	 *
	 * @since 0.5.0
	 *
	 * @param Query_Context $context    Validated input.
	 * @param Date_Range    $range      Range.
	 * @param array         $group_keys Resource IDs.
	 * @return array<string|int, array<string, mixed>>|\WP_Error
	 */
	protected function get_traffic( Query_Context $context, Date_Range $range, array $group_keys ): array|\WP_Error {
		return $this->source->get_resources( $context, $range, array_map( 'intval', $group_keys ), count( $group_keys ), 0 );
	}

	/**
	 * Page labels.
	 *
	 * @since 0.5.0
	 *
	 * @param array<string, mixed> $traffic_row Traffic row.
	 * @param Query_Context        $context     Validated input.
	 * @return array<string, mixed>
	 */
	protected function shape_labels( array $traffic_row, Query_Context $context ): array {
		return array_intersect_key( $this->source->shape_labels( $traffic_row ), $this->get_label_properties() );
	}

	/**
	 * Label schema.
	 *
	 * @since 0.5.0
	 *
	 * @return array<string, array<string, mixed>>
	 */
	protected function get_label_properties(): array {
		return array(
			'resource_type' => array( 'type' => 'string' ),
			'title'         => array( 'type' => array( 'string', 'null' ) ),
			'url'           => array( 'type' => array( 'string', 'null' ) ),
			'path'          => array( 'type' => array( 'string', 'null' ) ),
			'post_id'       => array( 'type' => array( 'integer', 'null' ) ),
			'post_type'     => array( 'type' => array( 'string', 'null' ) ),
		);
	}
}
