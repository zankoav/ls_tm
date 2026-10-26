<?php
/**
 * Plugin Name: LS_TM
 * Description: A personal, self-hosted task manager for WordPress.
 * Version: 0.1.0
 * Requires at least: 6.3
 * Requires PHP: 8.1
 * Author: LS_TM
 * Text Domain: ls-tm
 */

defined( 'ABSPATH' ) || exit;

define( 'LS_TM_VERSION', '0.1.0' );
define( 'LS_TM_FILE', __FILE__ );
define( 'LS_TM_DIR', plugin_dir_path( __FILE__ ) );

require_once LS_TM_DIR . 'includes/class-ls-tm-plugin.php';
require_once LS_TM_DIR . 'includes/class-ls-tm-rest-controller.php';

/**
 * WordPress normally requires HTTPS before it exposes Application Passwords.
 * The local LS_TM site runs only on localhost, where a separately revocable
 * Application Password is needed for the scheduled REST API automation.
 */
add_filter( 'wp_is_application_passwords_available', function( $available ) {
	$host = wp_parse_url( home_url(), PHP_URL_HOST );

	return $available || in_array( $host, array( 'localhost', '127.0.0.1' ), true );
} );

function ls_tm() {
	return LS_TM_Plugin::instance();
}

register_activation_hook( LS_TM_FILE, array( 'LS_TM_Plugin', 'activate' ) );
register_deactivation_hook( LS_TM_FILE, array( 'LS_TM_Plugin', 'deactivate' ) );

ls_tm();
