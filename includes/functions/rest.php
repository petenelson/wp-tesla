<?php
/**
 * WordPress REST API functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\REST;

use \WPTesla\User;

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
				'email' => [
					'required' => true,
					'sanitize_callback' => 'sanitize_email',
				],
				'password' => [
					'required' => true,
				],
			],
		]
	);

	register_rest_route(
		'wp-tesla/v1',
		'/logout',
		[
			'methods'  => [ 'POST' ],
			'callback' => __NAMESPACE__ . '\handle_logout',
			'permission_callback' => __NAMESPACE__ . '\user_can_authenticate',
		]
	);
}

/**
 * Verifies the current user can perform a login/authentication.
 *
 * @return bool
 */
function user_can_authenticate() {
	// TODO change this to a cap check.
	return apply_filters( __FUNCTION__, is_user_logged_in() );
}


/**
 * Performs authentication against the Tesla API.
 *
 * @param  WP_REST_Request $request The REST request.
 * @return WP_REST_Response
 */
function handle_authenticate( $request ) {

	$response = \WPTesla\API\authenticate(
		$request['email'],
		trim( $request['password'] ),
		get_current_user_id()
	);

	return rest_ensure_response( $response );
}

/**
 * Handles logging out the user.
 *
 * @return WP_REST_Response
 */
function handle_logout() {

	User\logout();

	$response['logged_out'] = true;

	return rest_ensure_response( $response );
}
