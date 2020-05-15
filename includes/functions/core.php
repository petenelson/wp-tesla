<?php
/**
 * Core plugin functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\Core;

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
	add_action( 'init', $n( 'register_scripts_styles' ) );
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
 * Register scripts and styles.
 *
 * @return void
 */
function register_scripts_styles() {
	wp_register_script(
		'wp-tesla-admin',
		WP_TESLA_URL . '/dist/js/admin.js',
		[],
		WP_TESLA_VERSION,
		true
	);

	wp_register_script(
		'wp-tesla',
		WP_TESLA_URL . '/dist/js/frontend.js',
		[],
		WP_TESLA_VERSION,
		true
	);

	wp_register_style(
		'wp-tesla-admin',
		WP_TESLA_URL . '/dist/css/admin-style.css',
		[],
		WP_TESLA_VERSION
	);
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
	wp_enqueue_script( 'wp-tesla-admin' );
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
	wp_enqueue_style( 'wp-tesla-admin' );
}
