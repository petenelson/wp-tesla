<?php
/**
 * Core plugin functionality.
 *
 * @package WP Tesla
 */

namespace PeteNelson\WPTesla\Core;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'init', $n( 'i18n' ) );
	add_action( 'admin_init', $n( 'init' ) );
	add_action( 'wp_enqueue_scripts', $n( 'scripts' ) );
	add_action( 'wp_enqueue_scripts', $n( 'styles' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_scripts' ) );
	add_action( 'admin_enqueue_scripts', $n( 'admin_styles' ) );
}

/**
 * Registers the default textdomain.
 *
 * @return void
 */
function i18n() {
	$locale = apply_filters( 'plugin_locale', get_locale(), 'wp-tesla' );
	load_textdomain( 'wp-tesla', WP_LANG_DIR . '/wp-tesla/wp-tesla-' . $locale . '.mo' );
	load_plugin_textdomain( 'wp-tesla', false, plugin_basename( WP_TESLA_PATH ) . '/lang/' );
}

/**
 * Performs any plugin upgrades due to version changes.
 *
 * @return void
 */
function upgrade() {

}

/**
 * Deactivate the plugin
 *
 * Uninstall routines should be in uninstall.php
 *
 * @return void
 */
function deactivate() {

}

/**
 * Enqueue scripts for front-end.
 *
 * @return void
 */
function scripts() {

}

/**
 * Enqueue scripts for admin.
 *
 * @return void
 */
function admin_scripts() {

}

/**
 * Enqueue styles for front-end.
 *
 * @return void
 */
function styles() {

}

/**
 * Enqueue styles for admin.
 *
 * @return void
 */
function admin_styles() {

}
