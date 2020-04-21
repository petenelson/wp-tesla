<?php
/**
 * API wrapper for calling the Tesla API.
 *
 * @package WP Tesla
 */

namespace WPTesla\API;

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

	$caching_enabled = apply_filters( __FUNCTION__ . '_caching_enabled', true );
	$max_retries     = apply_filters( __FUNCTION__ . '_max_retries', 5 );
	$retries         = 0;

	$response = null;
	$endpoint = get_base_url() . $endpoint;

	if ( empty( filter_var( $endpoint, FILTER_VALIDATE_URL ) ) ) {
		return false;
	}

	$params = wp_parse_args(
		$params,
		[
			// Lets us fine tune the cache time for requests.
			'cache_response' => true,
			'cache_time'     => 0,
			'body'           => false,
		]
	);

	$request_args = [
		'method'      => $method,
		'sslverify'   => false,
		'headers'     => [
			'Content-type'  => 'application/json',
		],
	];

	$request_args = array_merge( $request_args, (array) $args );

	// Convert the data in the body item to JSON.
	if ( ! empty( $params['body'] ) && is_array( $params['body'] ) ) {
		$request_args['body'] = json_encode( $params['body'] );
	}

	if ( 'PATCH' === $method || 'DELETE' === $method ) {
		$caching_enabled = false;
	}

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

			return apply_filters( __FUNCTION__, $api_response, $method, $params, $request_args );
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

				$cache_time = empty( $params['cache_time'] ) ? get_cache_time() : $params['cache_time'];

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

	return apply_filters( __FUNCTION__, $api_response );
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
		$increment = current_time( 'timestamp' );
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
 * Converts an API response to WP_Error if the response is an error.
 *
 * @param  array $response The response data.
 * @return mixed
 */
function response_to_error( $response ) {

	if ( isset( $response['data'] ) && is_array( $response['data'] ) && ! empty( $response['data'] ) ) {
		$data = $response['data'][0];
		if ( isset( $data->error_code ) && $data->message ) {
			return new \WP_Error( $data->error_code, $data->message, $response );
		}
	}

	return $response;
}

/**
 * Gets a random API caching time in seconds.
 *
 * @param int $min_seconds Minimum seconds, defaults to 15.
 * @param int $max_seconds Maximum seconds, defaults to 20.
 * @return int
 */
function get_cache_time( $min_seconds = MINUTE_IN_SECONDS * 15, $max_seconds = MINUTE_IN_SECONDS * 20 ) {
	return apply_filters( __FUNCTION__, mt_rand( $min_seconds, $max_seconds ) );
}

/**
 * Get the base API URL.
 *
 * @return string
 */
function get_base_url() {
	return apply_filters( __FUNCTION__, 'https://owner-api.teslamotors.com' );
}

/**
 * Gets an API token. Automatically refreshes the token if-needed.
 *
 * @param bool $should_refresh Flag to force a token refresh.
 * @return string
 */
function get_token( $should_refresh = false ) {

	// How soon before the token expires should we refresh it?
	$buffer = get_expire_buffer();

	$token            = get_option( get_token_key() );
	$expire_timestamp = get_option( get_expire_key() );
	$current_time     = current_time( 'timestamp' );

	if ( empty( $expire_timestamp ) ) {
		$expire_timestamp = $current_time;
		update_option( $expire_key, $expire_timestamp );
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
		$token = refresh_token();
	}

	return $token;
}

/**
 * Gets the buffer time in seconds for token expiration. Allows us to
 * refresh the token before it's had expired in the API infrastructure.
 *
 * @return int
 */
function get_expire_buffer() {
	return absint( apply_filters( __FUNCTION__, MINUTE_IN_SECONDS ) );
}

/**
 * The option key for storing the token.
 *
 * @return string
 */
function get_token_key() {
	return apply_filters( __FUNCTION__, 'wp_tesla_token' );
}

/**
 * The option key for storing the token expiration timestamp.
 *
 * @return string
 */
function get_expire_key() {
	return apply_filters( __FUNCTION__, 'wp_tesla_token_expiration_timestamp' );
}

/**
 * Refreshes the API token.
 *
 * @return string
 */
function refresh_token() {

	$token = false;

	$endpoint = get_base_url();
	$endpoint = add_query_arg( 'grant_type', 'client_credentials', $endpoint );
	$endpoint = apply_filters( __FUNCTION__ . '_endpoint', $endpoint );

	$ids = get_api_account_ids();

	$params = array(
		'timeout' => 10,
		'headers' => array(
			'Content-Type'  => 'application/x-www-form-urlencoded',
			'Authorization' => 'Basic ' . base64_encode( $ids['client'] . ':' . $ids['secret'] ),
		),
	);

	$response      = wp_remote_post( $endpoint, $params );
	$response_code = wp_remote_retrieve_response_code( $response );
	$body          = wp_remote_retrieve_body( $response );

	if ( 200 === absint( $response_code ) ) {
		$token_data = json_decode( $body, true );
		if ( ! empty( $token_data ) ) {

			invalidate_api_cache();

			$token             = sanitize_text_field( $token_data['access_token'] );
			$expires_timestamp = current_time( 'timestamp' ) + absint( $token_data['expires_in'] );

			update_option( get_token_key(), $token );
			update_option( get_expire_key(), $expires_timestamp );
		}
	}

	return apply_filters( __FUNCTION__, $token );
}

/**
 * Gets the API client ID.
 *
 * @return string
 */
function get_client_id() {
	return get_option( 'wp_tesla_client_id', '81527cff06843c8634fdc09e8ac0abefb46ac849f38fe1e431c2ef2106796384' );
}

/**
 * Gets the API client secret.
 *
 * @return string
 */
function get_client_secret() {
	return get_option( 'wp_tesla_client_secret', 'c7257eb71a564034f9419ee651c7d0e5f7aa6bfbd18bafb5c5c033b093bb2fa3' );
}
