<?php
/**
 * Cache helpers.
 *
 * @package WP Tesla
 */

namespace WPTesla\Cache;

/**
 * Returns a random cache time, defaults to between 11 and 12 hours.
 *
 * @param  integer $min Min time in seconds.
 * @param  integer $max Max time in seconds.
 * @return integer
 */
function get_cache_time( $min = HOUR_IN_SECONDS * 11, $max = HOUR_IN_SECONDS * 12 ) {

	if ( $max <= $min ) {
		$max = $min + HOUR_IN_SECONDS;
	}

	return apply_filters( 'wp_tesla_get_cache_time', mt_rand( $min, $max ) );
}

/**
 * Returns a custom value used to invalidate cache keys.
 *
 * @return string
 */
function get_cache_invalidator() {
	return wp_cache_get_last_changed( 'posts' );
}

/**
 * Gets a cached query. If no cached query is present, it will be
 * executed and cached.
 *
 * @param  array $query_args The query args.
 * @param  array $args       Additional args for the function.
 * @return WP_Query
 */
function get_cached_query( $query_args, $args = [] ) {

	$args = wp_parse_args(
		$args,
		[
			// Prefix for the cache key.
			'key_prefix' => 'wp_tesla_get_cached_query_',

			// Results of this callback are used when building the cache
			// key to allow for invalidation.
			'key_invalidator_callback' => 'get_cache_invalidator',

			// The function called to get the cache time.
			'cache_time_callback' => 'get_cache_time',

			// Default time if the callback isn't used.
			'cache_time' => MINUTE_IN_SECONDS * 15,

			// Do we want to force this query to be flushed from the cache?
			'flush' => false,
		]
	);

	$invalidator = is_callable( $args['key_invalidator_callback'] ) ? call_user_func( $args['key_invalidator_callback'] ) : '';

	$cache_key = $args['key_prefix'] . md5( wp_json_encode( $query_args ) . $invalidator );

	if ( true === $args['flush'] ) {
		wp_cache_delete( $cache_key );
	}

	$cached_query = wp_cache_get( $cache_key );
	if ( false !== $cached_query ) {
		return $cached_query;
	} else {

		$query = new \WP_Query( $query_args );

		if ( is_callable( $args['cache_time_callback'] ) ) {
			$cache_time = call_user_func( $args['cache_time_callback'] );
		} else if ( ! empty( $args['cache_time'] ) ) {
			$cache_time = $args['cache_time'];
		}

		wp_cache_set( $cache_key, $query, '', $cache_time );

		return $query;
	}
}
