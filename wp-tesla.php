<?php // phpcs:disable
/*
Plugin Name: WP Tesla
Description: Display your Tesla's information and status on your site.
Plugin URI: https://github.com/petenelson/wp-tesla
Version: 0.1.1
Author: Pete Nelson (@CodeGeekATX)
Text Domain: wp-tesla
Domain Path: /lang
*/
// phpcs:enable

if ( ! defined( 'ABSPATH' ) ) {
	return;
}

// Useful global constants.
define( 'WP_TESLA_VERSION', '0.1.1' );
define( 'WP_TESLA_URL', trailingslashit( plugin_dir_url( __FILE__ ) ) );
define( 'WP_TESLA_PATH', trailingslashit( plugin_dir_path( __FILE__ ) ) );
define( 'WP_TESLA_INC', WP_TESLA_PATH . 'includes/' );

// Include files.
require_once WP_TESLA_INC . 'functions/core.php';
require_once WP_TESLA_INC . 'functions/api.php';
require_once WP_TESLA_INC . 'functions/rest.php';
require_once WP_TESLA_INC . 'functions/user.php';
require_once WP_TESLA_INC . 'post-types/tesla.php';

// Activation/Deactivation.
register_activation_hook( __FILE__, '\WPTesla\Core\activate' );
register_deactivation_hook( __FILE__, '\WPTesla\Core\deactivate' );

// Bootstrap.
\WPTesla\Core\setup();
\WPTesla\User\setup();
\WPTesla\REST\setup();
\WPTesla\PostTypes\Tesla\setup();
