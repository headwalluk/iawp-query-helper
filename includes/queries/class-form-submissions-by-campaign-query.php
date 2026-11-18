<?php
/**
 * Query: form submissions by campaign. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Submissions grouped by the campaign of the session they were made in.
 *
 * @since 0.5.0
 */
class Form_Submissions_By_Campaign_Query extends Dimension_Form_Breakdown_Query {

	/**
	 * Query name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form-submissions-by-campaign';
	}

	/**
	 * Label.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Form submissions by campaign', 'ability label; contact form entries grouped by marketing campaign', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Form submissions from UTM-tagged campaign traffic for a date range, from Independent Analytics Pro. dimension chooses whole campaigns or one UTM parameter, as in the campaigns query. Each row has submissions, conversion rate (submissions as a percentage of the row\'s visitors), visitors and sessions. Filter with form_id (from the form-submissions query). Only campaigns with at least one submission are listed, most first. Paged.', 'iawp-query-helper' );
	}
}
