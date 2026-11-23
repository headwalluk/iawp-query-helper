<?php
/**
 * Plugin Name: IAWP Query Helper
 * Plugin URI: https://headwall-hosting.com/plugins/iawp-query-helper/
 * Description: Read-only REST routes and Abilities for querying Independent Analytics visitor and view metrics, built for AI agents.
 * Version: 1.0.3
 * Requires at least: 6.9
 * Requires PHP: 8.2
 * Author: Paul Faulkner
 * Author URI: https://headwall-hosting.com/a-web-guys-blog/
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: iawp-query-helper
 * Domain Path: /languages
 *
 * @package IAWP_Query_Helper
 */

// Exit if accessed directly.
defined( 'ABSPATH' ) || die();

define( 'IAWPQH_VERSION', '1.0.3' );
define( 'IAWPQH_FILE', __FILE__ );
define( 'IAWPQH_PATH', plugin_dir_path( __FILE__ ) );
define( 'IAWPQH_URL', plugin_dir_url( __FILE__ ) );
define( 'IAWPQH_BASENAME', plugin_basename( __FILE__ ) );

require_once IAWPQH_PATH . 'constants.php';
require_once IAWPQH_PATH . 'functions-private.php';

require_once IAWPQH_PATH . 'includes/class-environment.php';
require_once IAWPQH_PATH . 'includes/class-date-range.php';
require_once IAWPQH_PATH . 'includes/class-date-range-resolver.php';
require_once IAWPQH_PATH . 'includes/class-query-context.php';
require_once IAWPQH_PATH . 'includes/class-query-runner.php';
require_once IAWPQH_PATH . 'includes/class-query.php';
require_once IAWPQH_PATH . 'includes/class-dimension-query.php';
require_once IAWPQH_PATH . 'includes/class-resource-query.php';
require_once IAWPQH_PATH . 'includes/class-revenue-query.php';
require_once IAWPQH_PATH . 'includes/class-dimension-revenue-query.php';
require_once IAWPQH_PATH . 'includes/class-form-breakdown-query.php';
require_once IAWPQH_PATH . 'includes/class-dimension-form-breakdown-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-catalogue-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-site-summary-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-top-posts-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-traffic-over-time-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-referrers-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-referrer-types-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-countries-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-cities-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-devices-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-campaigns-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-top-pages-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-landing-pages-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-page-stats-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-site-searches-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-not-found-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-ecommerce-summary-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-revenue-by-landing-page-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-revenue-by-referrer-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-revenue-by-campaign-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-form-submissions-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-form-submissions-by-page-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-form-submissions-by-referrer-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-form-submissions-by-campaign-query.php';
require_once IAWPQH_PATH . 'includes/queries/class-raw-views-query.php';
require_once IAWPQH_PATH . 'includes/class-query-registry.php';
require_once IAWPQH_PATH . 'includes/class-executor.php';
require_once IAWPQH_PATH . 'includes/class-rest-controller.php';
require_once IAWPQH_PATH . 'includes/class-abilities.php';
require_once IAWPQH_PATH . 'includes/class-plugin.php';

// GitHub auto-updates (admin, cron and WP-CLI only; front-end requests don't need it).
if ( is_admin() || ( defined( 'DOING_CRON' ) && DOING_CRON ) || ( defined( 'WP_CLI' ) && WP_CLI ) ) {
	require_once IAWPQH_PATH . 'includes/class-github-updater.php';
	new IAWP_Query_Helper\Github_Updater( IAWPQH_FILE, IAWP_Query_Helper\UPDATER_GITHUB_REPO, IAWPQH_VERSION, IAWP_Query_Helper\FILTER_UPDATER_ENABLED );
}

/**
 * Record the installed version on activation.
 *
 * Must-use installs never fire this; Plugin::check_first_run() covers them.
 *
 * @since 0.1.0
 */
function iawpqh_activate(): void {
	add_option( IAWP_Query_Helper\OPTION_VERSION, IAWPQH_VERSION, '', false );
}
register_activation_hook( __FILE__, 'iawpqh_activate' );

/**
 * Initialise the plugin.
 *
 * @since 0.1.0
 */
function iawpqh_run(): void {
	$plugin = IAWP_Query_Helper\Plugin::instance();
	$plugin->run();
}
iawpqh_run();
