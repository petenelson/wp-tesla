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
}
