<?php
/**
 * Query: pages where sessions start.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Pages ranked by entrances: where visitors arrive.
 *
 * @since 0.3.0
 */
class Landing_Pages_Query extends Resource_Query {

	/**
	 * Query name.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'landing-pages';
	}

	/**
	 * Label.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Landing pages', 'ability label; pages where visits start', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'The pages where visits start, for a date range, from Independent Analytics: pages ranked by entrances (sessions that began on the page), with the same fields as top-pages. Useful alongside Google Search Console, which reports the landing page for search clicks. Only pages with at least one entrance are listed. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Rank by entrances.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	protected function get_default_orderby(): string {
		return 'entrances';
	}

	/**
	 * Only pages where at least one session started.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	protected function get_having_sql(): string {
		return 'entrances > 0';
	}
}
