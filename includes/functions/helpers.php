<?php
/**
 * Helper functions.
 *
 * @package WP Tesla
 */

namespace WPTesla\Helpers;

/**
 * Gets a random caching time in seconds.
 *
 * @param int $min_seconds Minimum seconds, defaults to 15.
 * @param int $max_seconds Maximum seconds, defaults to 20.
 * @return int
 */
function get_cache_time( $min_seconds = MINUTE_IN_SECONDS * 15, $max_seconds = MINUTE_IN_SECONDS * 20 ) {
	return apply_filters( __FUNCTION__, mt_rand( $min_seconds, $max_seconds ) );
}

/**
 * Outputs a list of sanitized CSS class names.
 *
 * @param  array|string $classes List of class names.
 * @param  bool         $echo    Echo the list of class names (defaults to true).
 * @return void|array
 */
function output_css_classes( $classes, $echo = true ) {

	if ( is_string( $classes ) ) {
		$classes = explode( ' ', $classes );
	}

	$classes = implode( ' ', array_map( 'sanitize_html_class', (array) $classes ) );

	if ( $echo ) {
		echo $classes; // phpcs:ignore
	} else {
		return $classes;
	}
}
