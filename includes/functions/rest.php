<?php
/**
 * WordPress REST API functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\REST;

/**
 * WordPress hooks and filters.
 *
 * @return void
 */
function setup() {

	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'rest_api_init', $n( 'add_rest_endpoints' ) );
}

/**
 * Adds REST API endpoints.
 *
 * @return void
 */
function add_rest_endpoints() {

	register_rest_route(
		'wp-tesla/v1',
		'/authenticate',
		[
			'methods'  => [ 'POST' ],
			'callback' => __NAMESPACE__ . '\handle_authenticate',
			'permission_callback' => __NAMESPACE__ . '\user_can_authenticate',
			'args' => [
				'username' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
				'password' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_text_field',
				],
			],
		]
	);
}

/**
 * Verifies the current user can perform a login/authentication.
 *
 * @param  WP_REST_Request $request The REST request.
 * @return WP_REST_Response
 */
function user_can_authenticate( $request = null ) {

	return rest_ensure_response( [] );
}
