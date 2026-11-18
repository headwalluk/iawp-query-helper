<?php
/**
 * IAWP Query Helper: Uninstaller
 *
 * Runs when the plugin is deleted from the Plugins screen, not on deactivation.
 * The plugin keeps no user data; this removes its internal-state options and the
 * updater cache. Independent Analytics' own data is never touched.
 *
 * @package IAWP_Query_Helper
 */

namespace IAWP_Query_Helper;

// Bail out if not invoked by WordPress's uninstall handler.
defined( 'WP_UNINSTALL_PLUGIN' ) || die();

require_once __DIR__ . '/constants.php';
require_once __DIR__ . '/includes/class-github-updater.php';

global $wpdb;

// Keys are found by prefix so options from later versions go too, then deleted through the API.
// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching -- One-off prefix lookup at uninstall; there is no API for it.
$iawpqh_option_names = $wpdb->get_col(
	$wpdb->prepare(
		"SELECT option_name FROM {$wpdb->options} WHERE option_name LIKE %s",
		$wpdb->esc_like( OPTION_PREFIX ) . '%'
	)
);

foreach ( $iawpqh_option_names as $iawpqh_option_name ) {
	delete_option( $iawpqh_option_name );
}

Github_Updater::delete_cache( basename( __DIR__ ) );
