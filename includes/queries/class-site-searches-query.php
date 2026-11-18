<?php
/**
 * Query: what visitors searched for on the site.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Search result pages, one per search term.
 *
 * @since 0.3.0
 */
class Site_Searches_Query extends Resource_Query {

	/**
	 * Query name.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'site-searches';
	}

	/**
	 * Label.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Site searches', 'ability label; terms visitors typed into the site search box', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'What visitors searched for using the site\'s own search box, for a date range, from Independent Analytics: one row per search term (search_query) with views, visitors, sessions, exits and bounce rate. A strong source of content ideas: frequent searches show what visitors expect to find. Paged.', 'iawp-query-helper' );
	}

	/**
	 * Search result pages only.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	protected function get_fixed_resource_type(): ?string {
		return 'search';
	}
}
