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

	if ( 0 !== stripos( $endpoint, 'http' ) ) {
		$endpoint = get_base_url() . $endpoint;
	}

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

	$headers = [];

	if ( isset( $args['headers'] ) && is_array( $args['headers'] ) ) {
		$headers = array_merge( $request_args['headers'], $args['headers'] );
	}

	$request_args = array_merge( $request_args, (array) $args );

	$request_args['headers'] = $headers;

	// Convert the data in the body item to JSON.
	if ( ! empty( $params['body'] ) && is_array( $params['body'] ) ) {
		$request_args['body'] = json_encode( $params['body'] );
	}

	$noncached_methods = [
		'PUT',
		'POST',
		'PATCH',
		'DELETE',
	];

	if ( in_array( $method, $noncached_methods, true ) || ! $params['cache_response'] ) {
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
		$api_response['response']      = $response;

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

		if ( 'raw' === $params['return'] ) {
			$api_response['body'] = $body;
		}

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
					invalidate_api_cache();
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

		// Defaults to parsing JSON, pass 'raw' to return the raw response.
		'return'         => '',

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

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	// How soon before the token expires should we refresh it?
	$buffer = get_expire_buffer();

	$token            = get_user_option( get_token_key(), $user_id );
	$expire_timestamp = get_user_option( get_expires_key(), $user_id );
	$current_time     = time();

	if ( empty( $expire_timestamp ) ) {
		$expire_timestamp = $current_time;
		update_user_option( $user_id, get_expires_key(), $expire_timestamp );
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
		$refresh_results = refresh_token( $user_id );
		$token = isset( $refresh_results['token'] ) ? $refresh_results['token'] : false;
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
	$buffer = get_option( 'wp_tesla_token_expire_buffer', HOUR_IN_SECONDS * 6 );
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
 * The option key for storing the token creation timestamp.
 *
 * @return string
 */
function get_created_key() {
	return apply_filters( 'wp_tesla_api_get_created_key', 'wp_tesla_token_created_timestamp' );
}

/**
 * The option key for storing the token expiration timestamp.
 *
 * @return string
 */
function get_expires_key() {
	return apply_filters( 'wp_tesla_api_get_expires_key', 'wp_tesla_token_expires_timestamp' );
}

/**
 * Refreshes the user's API token.
 *
 * @param int $user_id The user ID.
 * @return array
 */
function refresh_token( $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$refresh_token = get_user_option( get_refresh_token_key(), $user_id );

	if ( empty( $refresh_token ) ) {
		return false;
	}

	$form_values = [
		'grant_type'     => 'refresh_token',
		'client_id'      => 'ownerapi',
		'refresh_token'  => $refresh_token,
		'scope'          => 'openid email offline_access',
	];

	$api_response = request(
		'https://auth.tesla.com/oauth2/v3/token',
		'POST',
		[
			'cache_response' => false,
			'require_token'  => false,
			'form'           => $form_values,
		],
		[
			'headers' => [
				'User-Agent'         => '',
				'x-tesla-user-agent' => '',
				'X-Requested-With'   => 'com.teslamotors.tesla',
			],
		]
	);

	$results = process_token_response( $api_response, $user_id );

	return apply_filters( 'wp_tesla_api_refresh_token', $results );
}

/**
 * Revokes the user's API token.
 *
 * @param string $token The user's API token.
 * @return void
 */
function revoke_token( $token ) {

	if ( empty( $token ) ) {
		return;
	}

	$form_values = [
		'token' => $token,
	];

	$api_response = request(
		'/oauth/revoke',
		'POST',
		[
			'cache_response' => false,
			'require_token'  => false,
			'form'           => $form_values,
		]
	);
}

/**
 * Processes authentication or refresh token responses.
 *
 * @param  array $api_response The API response.
 * @param  int   $user_id      The user ID.
 * @return array $results
 */
function process_token_response( $api_response, $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$results = [
		'authenticated'  => false,
		'response_code'  => false,
		'token'          => false,
		'user_id'        => $user_id,
		'response_code'  => $api_response['response_code'],
	];

	if ( ! empty( $api_response['data'] ) && is_object( $api_response['data'] ) ) {

		$data = $api_response['data'];

		if ( 200 === $results['response_code'] && isset( $data->access_token, $data->refresh_token, $data->expires_in ) ) {

			// Success!
			$results['authenticated'] = true;

			update_user_option( $user_id, get_token_key(), $data->access_token );
			update_user_option( $user_id, get_refresh_token_key(), $data->refresh_token );
			update_user_option( $user_id, get_created_key(), time() );

			// Store the expiration date, all UTC.
			update_user_option( $user_id, get_expires_key(), time() + $data->expires_in );

			$results['token'] = $data->access_token;
		}
	}

	return $results;
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
 * Gets the login form to display within the My Tesla Account page.
 *
 * @return string
 */
function get_login_form_url() {

	if ( ! is_user_logged_in() ) {
		return false;
	}

	$user_id = get_current_user_id();

	$code_verifier = get_user_option( 'wp_tesla_oauth2_code_verifier' );

	if ( empty( $code_verifier ) ) {
		$code_verifier = wp_generate_password( 43, false );
		update_user_option( $user_id, 'wp_tesla_oauth2_code_verifier', $code_verifier );
	}

	$code_challenge = hash( 'sha256', $code_verifier, true );
	$code_challenge = rtrim( strtr( base64_encode( $code_challenge ), '+/', '-_' ), '=' );

	$state = wp_generate_password( 12, false );

	$url = apply_filters( 'wp_tesla_authorize_v3_base_url', 'https://auth.tesla.com/oauth2/v3/authorize' );

	$url = add_query_arg(
		[
			'client_id'             => 'ownerapi',
			'code_challenge'        => rawurlencode( $code_challenge ),
			'code_challenge_method' => 'S256',
			'redirect_uri'          => rawurlencode( 'https://auth.tesla.com/void/callback' ),
			'response_type'         => 'code',
			'scope'                 => rawurlencode( 'openid email offline_access' ),
			'state'                 => rawurlencode( $state ),
		],
		$url
	);

	$url = apply_filters( 'wp_tesla_authorize_v3_url', $url );

	return $url;

	$api_response = request(
		$url,
		'GET',
		[
			'cache_response' => false,
			'require_token'  => false,
			'return'         => 'raw',
		]
	);

	$session_id = wp_remote_retrieve_cookie( $api_response['response'], 'tesla-auth.sid' );

	return $form;
}

/**
 * Authenticates the current user with the Tesla API (oauth2/v3/authorize).
 *
 * @param string $code The authorization code, generated via the login form.
 * @return array
 */
function authenticate_v3( $code ) {

	$user_id = get_current_user_id();

	$code_verifier = get_user_option( 'wp_tesla_oauth2_code_verifier' );

	$results = [
		'authenticated'  => false,
		'response_code'  => false,
		'token'          => false,
		'user_id'        => $user_id,
		'response_code'  => false,
	];

	if ( ! empty( $code ) ) {

		// Exchange authorization code for the access tokens.
		$form_values = [
			'grant_type'    => 'authorization_code',
			'client_id'     => 'ownerapi',
			'code'          => $code,
			'code_verifier' => $code_verifier,
			'redirect_uri'  => 'https://auth.tesla.com/void/callback',
		];

		$api_response = request(
			'https://auth.tesla.com/oauth2/v3/token',
			'POST',
			[
				'cache_response' => false,
				'require_token'  => false,
				'return'         => 'raw',
				'form'           => $form_values,
			],
			[
				'headers' => [
					'User-Agent'         => '',
					'x-tesla-user-agent' => '',
					'X-Requested-With'   => 'com.teslamotors.tesla',
				],
			]
		);

		$results = process_token_response( $api_response, $user_id );
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

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$api_response = request(
		'/api/1/vehicles',
		'GET',
		[
			'user_id' => $user_id,
		]
	);

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

/**
 * Replaces the vehicle ID in the URL with the supplied vehicle ID.
 *
 * @param  string $url        The URL.
 * @param  string $vehicle_id The vehcile ID.
 * @return string
 */
function vehicleize_url( $url, $vehicle_id ) {
	$url = str_replace( '{{vehicle_id}}', trim( $vehicle_id ), $url );
	return apply_filters( 'wp_tesla_api_vehicleize_url', $url, $vehicle_id );
}

/**
 * Gets the complete data for a vehicle.
 *
 * @param  string  $vehicle_id The vehicle ID.
 * @param  integer $user_id    The user ID.
 * @return array
 */
function vehicle_data( $vehicle_id, $user_id = 0 ) {

	$api_response = request(
		vehicleize_url( '/api/1/vehicles/{{vehicle_id}}/vehicle_data', $vehicle_id ),
		'GET',
		[
			'user_id' => empty( $user_id ) ? get_current_user_id() : $user_id,
		]
	);

	$vehicles = [];

	$response = false;

	if ( ! empty( $api_response['data'] ) && is_object( $api_response['data'] ) ) {
		$response = $api_response['data'];
	}

	return apply_filters( 'wp_tesla_api_vehicle_data', $response, $vehicle_id, $user_id );
}

/**
 * Wakes up the vehicle.
 *
 * @param  string  $vehicle_id The vehicle ID.
 * @param  integer $user_id    The user ID.
 * @return array
 */
function wakeup( $vehicle_id, $user_id = 0 ) {

	$api_response = request(
		vehicleize_url( '/api/1/vehicles/{{vehicle_id}}/wake_up', $vehicle_id ),
		'POST',
		[
			'user_id' => empty( $user_id ) ? get_current_user_id() : $user_id,
		]
	);

	if ( ! empty( $api_response['data'] ) && is_object( $api_response['data'] ) ) {
		$response = $api_response['data'];
	}

	return apply_filters( 'wp_tesla_api_wakeup', $response, $vehicle_id, $user_id );
}
