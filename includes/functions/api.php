<?php
/**
 * API wrapper for calling the Tesla API.
 *
 * @package WP Tesla
 */

namespace WPTesla\API;

use \WPTesla\Helpers;

/**
 * Send a request to the API. This is a wrapper for GET/POST/DELETE API requests.
 *
 * @param  string $endpoint The API endpoint URL.
 * @param  string $method   The request method (GET|POST|DELETE).
 * @param  array  $params   Array of API request params.
 * @param  array  $args     Array of request args to pass to wp_remote_request().
 * @return array
 */
function request( $endpoint, $method = 'GET', $params = [], $args = [] ) {

	// The main filter name used for the return value.
	$filter_name = 'wp_tesla_api_request';

	$caching_enabled = apply_filters( 'wp_tesla_api_caching_enabled', true );
	$max_retries     = apply_filters( 'wp_tesla_api_max_retries', 3 );
	$retries         = 0;

	$response = null;
	$endpoint = get_base_url() . $endpoint;

	if ( empty( filter_var( $endpoint, FILTER_VALIDATE_URL ) ) ) {
		return false;
	}

	$params = wp_parse_args(
		$params,
		get_default_request_params()
	);

	$request_args = [
		'method'      => $method,
		'sslverify'   => false,
		'headers'     => [
			'Content-type'  => 'application/json',
		],
	];

	if ( ! empty( $params['form'] ) ) {
		$request_args['body'] = http_build_query( $params['form'] );

		unset( $params['form'] );

		$request_args['headers']['Content-type'] = 'application/x-www-form-urlencoded';
	}

	$request_args = array_merge( $request_args, (array) $args );

	// Convert the data in the body item to JSON.
	if ( ! empty( $params['body'] ) && is_array( $params['body'] ) ) {
		$request_args['body'] = json_encode( $params['body'] );
	}

	if ( 'PATCH' === $method || 'DELETE' === $method || ! $params['cache_response'] ) {
		$caching_enabled = false;
	}

	if ( $params['require_token'] ) {
		$token = get_token( $params['user_id'] );
		if ( ! empty( $token ) ) {
			$request_args['headers']['Authorization'] = 'Bearer ' . trim( $token );
		}
	}

	// One last change to filter this before the API request.
	$request_args = apply_filters( "{$filter_name}_args", $request_args, $endpoint, $endpoint, $method, $params, $args );

	$cache_key = 'wp_tesla_api_request_' . md5( $endpoint . $method . json_encode( $params ) . json_encode( $request_args ) );

	$api_response = [
		'data'          => false,
		'cache_hit'     => false,
		'endpoint'      => $endpoint,
		'method'        => $method,
		'params'        => $params,
		'request_args'  => $request_args,
		'response_code' => false,
	];

	if ( $caching_enabled ) {
		$cached_response = wp_cache_get( $cache_key );
		if ( false !== $cached_response ) {

			$api_response['data']      = $cached_response;
			$api_response['cache_hit'] = true;

			return apply_filters( $filter_name, $api_response, $method, $params, $request_args, $args );
		}
	}

	$response = false;
	$token_refreshed = false;

	while ( $retries <= $max_retries ) {

		$response = wp_remote_request( $endpoint, $request_args );

		$api_response['response_code'] = wp_remote_retrieve_response_code( $response );

		if ( is_wp_error( $response ) ) {
			$response = false;
			$retries++;
			sleep( 3 * $retries );
		} else {
			break;
		}
	}

	if ( ! empty( $response ) ) {
		$body = wp_remote_retrieve_body( $response );
		$code = wp_remote_retrieve_response_code( $response );

		if ( ! empty( $body ) ) {

			$data = json_decode( $body );

			if ( ! empty( $data ) ) {
				$api_response['data'] = $data;

				$cache_time = empty( $params['cache_time'] ) ? Helpers\get_cache_time() : $params['cache_time'];

				$allowed_codes = [
					200,
					201,
				];

				if ( $caching_enabled && true === $params['cache_response'] && in_array( $code, $allowed_codes ) ) {
					wp_cache_set( $cache_key, $data, '', $cache_time );
				}
			}
		}
	}

	return apply_filters( $filter_name, $api_response, $method, $params, $request_args, $args );
}

/**
 * Gets the default API request parameters.
 *
 * @return array
 */
function get_default_request_params() {

	$params = [
		'user_id'        => get_current_user_id(),
		'body'           => false,
		'form'           => false,
		'require_token'  => true,
		'cache_response' => true,

		// Lets us fine tune the cache time for requests.
		'cache_time'     => 0,
	];

	return apply_filters( 'wp_tesla_api_get_default_request_params', $params );
}

/**
 * Gets the cache incremenetor.
 *
 * @return int
 */
function get_cache_increment() {

	$cache_key = 'wp_tesla_api_increment';
	$increment = wp_cache_get( $cache_key );
	if ( false === $increment ) {
		$increment = microtime();
		wp_cache_set( $cache_key, $increment, '', DAY_IN_SECONDS * 1 );
	}

	return $increment;
}

/**
 * Invalidates the API cache.
 *
 * @return void
 */
function invalidate_api_cache() {
	wp_cache_delete( 'wp_tesla_api_increment' );
}

/**
 * Get the base API URL.
 *
 * @return string
 */
function get_base_url() {
	return apply_filters( 'wp_tesla_api_get_base_url', 'https://owner-api.teslamotors.com' );
}

/**
 * Gets the user's API token. Automatically refreshes the token if-needed.
 *
 * @param int  $user_id        The user ID.
 * @param bool $should_refresh Flag to force a token refresh.
 * @return string
 */
function get_token( $user_id = 0, $should_refresh = false ) {

	// How soon before the token expires should we refresh it?
	$buffer = get_expire_buffer();

	$token            = get_user_option( get_token_key(), $user_id );
	$expire_timestamp = get_user_option( get_expire_key(), $user_id );
	$current_time     = time();

	if ( empty( $expire_timestamp ) ) {
		$expire_timestamp = $current_time;
		update_user_option( $user_id, get_expire_key(), $expire_timestamp );
	}

	// Do we have a token?
	if ( empty( $token ) ) {
		$should_refresh = true;
	}

	$expires_in_seconds = $expire_timestamp - $current_time;

	// Is the token expired or going to expire soon?
	if ( $expires_in_seconds < $buffer ) {
		$should_refresh = true;
	}

	if ( $should_refresh ) {
		$token = refresh_token( $user_id );
	}

	return apply_filters( 'wp_tesla_api_get_token', $token, $should_refresh );
}

/**
 * Gets the buffer time in seconds for token expiration. Allows us to
 * refresh the token before it has expired in the API infrastructure.
 *
 * @return int
 */
function get_expire_buffer() {
	$buffer = get_option( 'wp_tesla_token_expire_buffer', DAY_IN_SECONDS * 3 );
	return absint( apply_filters( 'wp_tesla_api_get_expire_buffer', $buffer ) );
}

/**
 * The option key for storing the token.
 *
 * @return string
 */
function get_token_key() {
	return apply_filters( 'wp_tesla_api_get_token_key', 'wp_tesla_token' );
}

/**
 * The option key for storing the refresh token.
 *
 * @return string
 */
function get_refresh_token_key() {
	return apply_filters( 'wp_tesla_api_get_refresh_token_key', 'wp_tesla_refresh_token' );
}

/**
 * The option key for storing the token expiration timestamp.
 *
 * @return string
 */
function get_expire_key() {
	return apply_filters( 'wp_tesla_api_get_expire_key', 'wp_tesla_token_expiration_timestamp' );
}

/**
 * Refreshes the user's API token.
 *
 * @param int $user_id The user ID.
 * @return bool
 */
function refresh_token( $user_id = 0 ) {
	// TODO.
	return false;
}

/**
 * Gets the API client ID.
 *
 * @return string
 */
function get_client_id() {
	$id = get_option( 'wp_tesla_client_id', '81527cff06843c8634fdc09e8ac0abefb46ac849f38fe1e431c2ef2106796384' );
	return apply_filters( 'wp_tesla_api_get_client_id', $id );
}

/**
 * Gets the API client secret.
 *
 * @return string
 */
function get_client_secret() {
	$secret = get_option( 'wp_tesla_client_secret', 'c7257eb71a564034f9419ee651c7d0e5f7aa6bfbd18bafb5c5c033b093bb2fa3' );
	return apply_filters( 'wp_tesla_api_get_client_secret', $secret );
}

/**
 * Authenticates the user with the Tesla API.
 *
 * @param  string $email    The email address.
 * @param  string $password The password.
 * @param  int    $user_id  The WordPress user ID, defaults to the
 *                          current user ID.
 * @return array
 */
function authenticate( $email, $password, $user_id = 0 ) {

	$results = [
		'authenticated' => false,
		'response_code' => false,
		'user_id'       => $user_id,
	];

	$form_values = [
		'grant_type'     => 'password',
		'client_id'      => get_client_id(),
		'client_secret'  => get_client_secret(),
		'email'          => $email,
		'password'       => $password,
	];

	$api_response = request(
		'/oauth/token',
		'POST',
		[
			'cache_response' => false,
			'require_token'  => false,
			'form'           => $form_values,
		]
	);

	$results['response_code'] = $api_response['response_code'];

	if ( ! empty( $api_response['data'] ) && is_object( $api_response['data'] ) ) {

		$data = $api_response['data'];

		if ( isset( $data->access_token, $data->refresh_token, $data->created_at, $data->expires_in ) ) {

			// Success!
			$results['authenticated'] = true;

			update_user_option( $user_id, get_token_key(), $data->access_token );
			update_user_option( $user_id, get_refresh_token_key(), $data->refresh_token );

			// Store the expiration date.
			$expires_at = $data->created_at + $data->expires_in;

			update_user_option( $user_id, get_expire_key(), $expires_at );
		}
	}

	return apply_filters( 'wp_tesla_api_authenticate', $results );
}

/**
 * Gets a list of vehicles for a user.
 *
 * @param  integer $user_id The user ID.
 * @return array
 */
function vehicles( $user_id = 0 ) {

	// TODO add list of options codes from https://raw.githubusercontent.com/timdorr/tesla-api/master/docs/vehicle/optioncodes.md.
	$api_response = request( '/api/1/vehicles' );

	$vehicles = [];

	if ( ! empty( $api_response['data'] ) && is_object( $api_response['data'] ) ) {
		if ( isset( $api_response['data']->response ) && is_array( $api_response['data']->response ) ) {
			foreach ( $api_response['data']->response as $api_response ) {
				$vehicles[] = (array) $api_response;
			}
		}
	}

	return apply_filters( 'wp_tesla_api_list_vehicles', $vehicles, $user_id );
}
