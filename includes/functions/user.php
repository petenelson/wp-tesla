<?php
/**
 * User-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\User;

/**
 * Set up theme defaults and register supported WordPress features.
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'admin_menu', $n( 'add_tesla_settings_menu' ) );
}

/**
 * Gets the menu slug for the settings page.
 *
 * @return string
 */
function get_settings_menu_slug() {
	return apply_filters( __FUNCTION__, \WPTesla\PostTypes\Tesla\get_post_type_name() . '-settings' );
}

/**
 * Adds a top-level menu page.
 *
 * @return void
 */
function add_tesla_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . \WPTesla\PostTypes\Tesla\get_post_type_name(),
		__( 'My Tesla Account', 'wp-tesla' ),
		__( 'My Account', 'wp-tesla' ),
		'edit_posts',
		get_settings_menu_slug(),
		__NAMESPACE__ . '\display_settings_page'
	);
}

/**
 * Displays the settings page.
 *
 * @return void
 */
function display_settings_page() {
	?>
		Hello world.
	<?php
}