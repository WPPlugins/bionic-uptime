<?php
/**
 * Remove everything the plugin stored: the key and the cached status.
 *
 * @package BionicUptime
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/**
 * Delete the plugin's data for the current site.
 */
function bionic_uptime_uninstall_site() {
	delete_option( 'bionic_uptime_key' );
	delete_transient( 'bionic_uptime_status' );
}

if ( is_multisite() ) {
	// A network-activated plugin may have a key saved on every site.
	$bionic_uptime_site_ids = get_sites(
		array(
			'fields' => 'ids',
			'number' => 0,
		)
	);
	foreach ( $bionic_uptime_site_ids as $bionic_uptime_site_id ) {
		switch_to_blog( $bionic_uptime_site_id );
		bionic_uptime_uninstall_site();
		restore_current_blog();
	}
} else {
	bionic_uptime_uninstall_site();
}
