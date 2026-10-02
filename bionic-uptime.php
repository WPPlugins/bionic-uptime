<?php
/**
 * Plugin Name:       Bionic Uptime
 * Plugin URI:        https://bionicuptime.com/wordpress
 * Description:       Shows this site's status from Bionic Uptime on your dashboard. Bionic Uptime checks the site from outside every 60 seconds and emails you when it is down.
 * Version:           1.0.1
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            Bionic Uptime
 * Author URI:        https://bionicuptime.com
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bionic-uptime
 *
 * @package BionicUptime
 */

/*
This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
GNU General Public License for more details.
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BIONIC_UPTIME_VERSION', '1.0.1' );
define( 'BIONIC_UPTIME_FILE', __FILE__ );
define( 'BIONIC_UPTIME_DIR', plugin_dir_path( __FILE__ ) );
define( 'BIONIC_UPTIME_URL', plugin_dir_url( __FILE__ ) );

// Option and transient names. uninstall.php repeats these literally,
// because it runs without this file being loaded.
define( 'BIONIC_UPTIME_OPTION_KEY', 'bionic_uptime_key' );
define( 'BIONIC_UPTIME_TRANSIENT', 'bionic_uptime_status' );

require_once BIONIC_UPTIME_DIR . 'includes/api.php';

if ( is_admin() ) {
	require_once BIONIC_UPTIME_DIR . 'includes/settings.php';
	require_once BIONIC_UPTIME_DIR . 'includes/dashboard.php';
}
