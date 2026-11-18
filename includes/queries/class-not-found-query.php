<?php
/**
 * Query: requested URLs that returned 404.
 *
 * @package IAWP_Query_Helper
 * @since 0.3.0
 */

namespace IAWP_Query_Helper;

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

/**
 * 404 pages, one per requested path.
 *
 * @since 0.3.0
 */
class Not_Found_Query extends Resource_Query {

	/**
	 * Query name.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_name(): string {
		return 'not-found';
	}

	/**
	 * Label.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_label(): string {
		return _x( 'Not found pages', 'ability label; requested addresses that returned a 404 error', 'iawp-query-helper' );
	}

	/**
	 * Description for agents.
	 *
	 * @since 0.3.0
	 *
	 * @return string
	 */
	public function get_description(): string {
		return __( 'Requested URLs that returned a 404 Not Found error, for a date range, from Independent Analytics: one row per requested path (not_found_url) with views, visitors and sessions. Shows broken links and pages visitors expect to exist, which may need a redirect or new content. Query strings and fragments are removed from these URLs, so two rows can share a path when Independent Analytics recorded them with different query strings. Paged.', 'iawp-query-helper' );
	}

	/**
	 * 404 pages only.
	 *
	 * @since 0.3.0
	 *
	 * @return string|null
	 */
	protected function get_fixed_resource_type(): ?string {
		return '404';
	}
}
