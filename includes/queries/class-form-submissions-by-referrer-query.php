<?php
/**
 * Query: form submissions by referrer. Independent Analytics Pro only.
 *
 * @package IAWP_Query_Helper
 * @since 0.5.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Submissions grouped by the referrer of the session they were made in.
 *
 * @since 0.5.0
 */
class Form_Submissions_By_Referrer_Query extends Dimension_Form_Breakdown_Query {

	/**
	 * Query name.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'form-submissions-by-referrer';
	}

	/**
	 * Label.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Form submissions by referrer', 'ability label; contact form entries grouped by the website that sent the visitor', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.5.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Form submissions by traffic source for a date range, from Independent Analytics Pro: each referrer (Google, Facebook, Direct...) with submissions, conversion rate (submissions as a percentage of the referrer\'s visitors), visitors and sessions. Matches the form columns of the dashboard\'s Referrers table. Filter with form_id (from the form-submissions query) and referrer_type. Only referrers with at least one submission are listed, most first. Paged.', 'iawp-query-helper' );
	}
}
