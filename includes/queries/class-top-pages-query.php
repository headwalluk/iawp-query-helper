<?php
/**
 * Query: every kind of page, ranked by traffic.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * Every resource IAWP records, as on the dashboard's Pages table.
 *
 * @since 0.3.0
 */
class Top_Pages_Query extends Resource_Query {

	/**
	 * Query name.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'top-pages';
	}

	/**
	 * Label.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Top pages', 'ability label; most-visited pages of any kind', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Every kind of page Independent Analytics records (posts, pages, products, the home page, category and tag archives, search results, 404s) ranked by views, visitors, sessions, entrances or exits for a date range. Each row has the page title, absolute URL, path, kind and, for posts, the post ID and post type, plus views, visitors, sessions, entrances, exits, exit rate, bounces, bounce rate, views per session and average time on page. Matches the dashboard\'s Pages table. Filter with resource_type, post_type or an exact url (for example a URL from Google Search Console). Visitors must not be summed across rows. Paged.', 'iawp-query-helper' );
	}
}
