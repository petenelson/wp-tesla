<?php
/**
 * Template-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\Templates;

/**
 * Set up theme defaults and register supported WordPress features.
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_filter( 'the_content', $n( 'maybe_replace_vehicle_content' ) );
}

/**
 * Replaces the vehicle content for the_content filter.
 *
 * @param string $content The post content.
 * @return string
 */
function maybe_replace_vehicle_content( $content ) {

	if ( is_singular( \WPTesla\PostTypes\Tesla\get_post_type_name() ) ) {
		ob_start();
		require WP_TESLA_PATH . 'partials/vehicle-content.php';
		$content = ob_get_clean();
	}

	return $content;
}
