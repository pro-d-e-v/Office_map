<?php
/**
 * Uninstall routine: remove all plugin data when the plugin is deleted.
 *
 * Runs only when the user deletes the plugin from the Plugins screen
 * (not on deactivation).
 *
 * @package Office_Map
 */

// If uninstall.php is not called by WordPress, bail out.
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Remove the plugin data for the current site.
 *
 * @return void
 */
function office_map_uninstall_site() {
	// Options.
	delete_option( 'office_map_options' );
	delete_option( 'office_map_options_backup' );
	delete_option( 'office_map_options_lastgood' );

	// Transients left by the geocoder (per-query cache and rate limits).
	global $wpdb;
	$wpdb->query( // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
		$wpdb->prepare(
			"DELETE FROM {$wpdb->options} WHERE option_name LIKE %s OR option_name LIKE %s",
			$wpdb->esc_like( '_transient_office_map_geo_' ) . '%',
			$wpdb->esc_like( '_transient_timeout_office_map_geo_' ) . '%'
		)
	);
}

if ( is_multisite() ) {
	$office_map_site_ids = get_sites( array( 'fields' => 'ids' ) );
	foreach ( $office_map_site_ids as $office_map_site_id ) {
		switch_to_blog( (int) $office_map_site_id );
		office_map_uninstall_site();
		restore_current_blog();
	}
} else {
	office_map_uninstall_site();
}
