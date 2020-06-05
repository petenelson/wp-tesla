<?php
/**
 * Vehicle-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\Vehicle;

use \WPTesla\API;
use \WPTesla\Cache;
use \WPTesla\PostTypes\Tesla;
use \WPTesla\Taxonomies\OptionCode;

/**
 * Set up theme defaults and register supported WordPress features.
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// Cron handler for automatic syncing of the charge state.
	add_action( 'wp_tesla_sync_vehicle_charge_state', $n( 'get_charge_state' ) );
}

/**
 * Gets an existing vehicle.
 *
 * @param string $vehicle_id The Tesla vehicle ID.
 * @param int    $user_id    The WP user ID.
 * @return WP_Post
 */
function get_existing_vehicle( $vehicle_id, $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$vehicle_id = trim( $vehicle_id );

	if ( empty( $vehicle_id ) ) {
		return false;
	}

	$query_args = [
		'post_type'              => Tesla\get_post_type_name(),
		'posts_per_page'         => 1,
		'update_post_meta_cache' => false,
		'update_term_meta_cache' => false,
		'author'                 => $user_id,
		'meta_key'               => get_vehicle_id_meta_prefix() . $vehicle_id,
		'meta_value'             => $vehicle_id,
	];

	$query = Cache\get_cached_query( $query_args );

	$vehicle = ! empty( $query->posts ) ? $query->posts[0] : false;

	return apply_filters( 'wp_tesla_get_existing_vehicle', $vehicle, $user_id );
}

/**
 * Gets the vehicle post for the current post.
 *
 * @return array Vehicle and ID.
 */
function get_the_vehicle() {

	$results = [];

	if ( is_singular( \WPTesla\PostTypes\Tesla\get_post_type_name() ) ) {
		$results['post']       = get_queried_object();
		$results['vehicle_id'] = get_vehicle_id( $results['post']->ID );
		$results['user_id']    = $results['post']->post_author;
	}

	return apply_filters( 'wp_tesla_get_the_vehicle', $results );
}

/**
 * Gets the meta key prefix for storing the vehicle ID.
 *
 * @return string
 */
function get_vehicle_id_meta_prefix() {
	return apply_filters( 'wp_tesla_get_vehicle_id_meta_prefix', 'wp_tesla_vehicle_id_' );
}

/**
 * Gets the vehicle ID from a post.
 *
 * @param  int $post_id The post ID.
 * @return string
 */
function get_vehicle_id( $post_id ) {

	$cache_key      = 'wp_tesla_vehicle_id_' . $post_id . '_' . Cache\get_cache_invalidator();
	$cached_results = wp_cache_get( $cache_key );
	if ( false !== $cached_results ) {
		return apply_filters( 'wp_tesla_get_vehicle_id', $cached_results, $post_id );
	}

	$meta       = get_post_meta( $post_id );
	$vehicle_id = false;
	$prefix     = get_vehicle_id_meta_prefix();

	if ( is_array( $meta ) ) {
		foreach ( $meta as $key => $value ) {
			if ( 0 === stripos( $key, $prefix ) && is_array( $value ) && ! empty( $value ) ) {

				$vehicle_id = $value[0];

				wp_cache_set( $cache_key, $vehicle_id, '', Cache\get_cache_time() );

				break;
			}
		}
	}

	return apply_filters( 'wp_tesla_get_vehicle_id', $vehicle_id, $post_id );
}

/**
 * Gets the meta key for storing the charge state data.
 *
 * @return string
 */
function get_charge_state_key() {
	return apply_filters( 'wp_tesla_vehicle_charge_state_key', 'wp_tesla_vehicle_charge_state' );
}

/**
 * Gets the meta key for storing the charge state updated timestamp.
 *
 * @return string
 */
function get_charge_state_updated_key() {
	return apply_filters( 'wp_tesla_vehicle_charge_state_updated_key', 'wp_tesla_vehicle_charge_state_updated' );
}

/**
 * Gets the meta key for storing the VIN.
 *
 * @return string
 */
function get_vin_key() {
	return apply_filters( 'wp_tesla_vehicle_vin_key', 'wp_tesla_vehicle_vin' );
}

/**
 * Updates or creates a Tesla vehicle post
 *
 * @param string $vehicle_id   The Tesla vehicle ID.
 * @param int    $user_id      The WP user ID.
 * @param array  $vehicle_data The vehicle data from the API.
 *
 * @return WP_Post
 */
function sync_vehicle( $vehicle_id, $user_id, $vehicle_data ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$vehicle_id = trim( $vehicle_id );

	$vehicle_data = wp_parse_args(
		$vehicle_data,
		[
			'display_name' => '',
			'vin'          => '',
			'state'        => '',
			'option_codes' => '',
		]
	);

	$vehicle_data['option_codes'] = explode( ',', $vehicle_data['option_codes'] );

	$vehicle = get_existing_vehicle( $vehicle_id, $user_id );

	// Create a new vehicle.
	if ( empty( $vehicle ) ) {

		$postarr = [
			'post_type'   => Tesla\get_post_type_name(),
			'post_status' => 'publish',
			'post_title'  => $vehicle_data['display_name'],
			'post_name'   => sanitize_title( $vehicle_data['display_name'] ),
			'post_author' => $user_id,
		];

		$post_id = wp_insert_post( $postarr );

		// Flag it with the vehicle ID so we can find it again.
		if ( ! empty( $post_id ) ) {
			update_post_meta( $post_id, get_vehicle_id_meta_prefix() . $vehicle_id, $vehicle_id );
			$vehicle = get_post( $post_id );
		}
	}

	if ( ! empty( $vehicle ) ) {

		$postarr = [
			'ID'          => $vehicle->ID,
			'post_title'  => $vehicle_data['display_name'],
			'post_name'   => sanitize_title( $vehicle_data['display_name'] ),
		];

		wp_update_post( $postarr );

		// Update the various vehicle items that don't change.
		update_post_meta( $vehicle->ID, get_vin_key(), sanitize_text_field( $vehicle_data['vin'] ) );

		$option_code_ids = [];

		foreach ( $vehicle_data['option_codes'] as $slug ) {
			$term = OptionCode\get_option_code( $slug );
			if ( ! empty( $term ) ) {
				$option_code_ids[] = $term->term_id;
			}
		}

		wp_set_post_terms( $vehicle->ID, $option_code_ids, OptionCode\get_taxonomy_name(), false );

		$vehicle = get_post( $vehicle->ID );
	}

	maybe_create_sync_crons( $vehicle_id, $user_id );

	return apply_filters( 'wp_tesla_sync_vehicle', $vehicle, $vehicle_id, $user_id );
}

/**
 * How often should a vehicle charge state be synced in seconds, defaults
 * to one hour.
 *
 * @return int
 */
function get_charge_sync_interval() {
	return apply_filters( 'wp_tesla_charge_sync_interval', HOUR_IN_SECONDS * 1 );
}

/**
 * Get the charge state data for a vehicle. Automatically syncs charge
 * state from the API to the post meta if-needed.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @param  array  $args       Additional args.
 * @return array
 */
function get_charge_state( $vehicle_id, $user_id = 0, $args = [] ) {

	$args = wp_parse_args(
		$args,
		[
			'field' => false,
		]
	);

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$vehicle = get_existing_vehicle( $vehicle_id, $user_id );

	$charge_data = false;

	if ( ! empty( $vehicle ) ) {

		$now          = time();
		$charge_data  = get_post_meta( $vehicle->ID, get_charge_state_key(), true );
		$last_updated = absint( get_post_meta( $vehicle->ID, get_charge_state_updated_key(), true ) );
		$needs_sync   = empty( $charge_data );

		// Check the seconds since the last sync with the current time.
		if ( ! $needs_sync && ( $now - $last_updated ) > get_charge_sync_interval() ) {
			$needs_sync = true;
		}

		if ( $needs_sync ) {
			sync_charge_state( $vehicle_id, $user_id );
			$charge_data = get_post_meta( $vehicle->ID, get_charge_state_key(), true );
		}

		if ( ! empty( $charge_data ) ) {
			$charge_data = json_decode( $charge_data );
			if ( is_object( $charge_data ) ) {
				$charge_data = (array) $charge_data;
			}
		}

		maybe_create_sync_crons( $vehicle_id, $user_id );
	}

	// Do we only want a single field?
	if ( ! empty( $args['field'] ) ) {
		$charge_data = is_array( $charge_data ) && isset( $charge_data[ $args['field'] ] ) ? $charge_data[ $args['field'] ] : false;
	}

	return apply_filters( 'wp_tesla_vehicle_get_charge_state', $charge_data, $vehicle_id, $user_id );
}

/**
 * Get the vehicle VIN.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @return string
 */
function get_vin( $vehicle_id, $user_id = 0 ) {

	$vin = false;

	$vehicle = get_existing_vehicle( $vehicle_id, $user_id );

	if ( is_a( $vehicle, '\WP_Post' ) ) {
		$vin = get_post_meta( $vehicle->ID, get_vin_key(), true );
	}

	return apply_filters( 'wp_tesla_vehicle_get_vin', $vin, $vehicle_id, $user_id );
}

/**
 * Get the battery level for a vehicle.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @return int
 */
function get_battery_level( $vehicle_id, $user_id = 0 ) {

	$battery_level = get_charge_state( $vehicle_id, $user_id, [ 'field' => 'usable_battery_level' ] );

	if ( false !== $battery_level ) {
		$battery_level = absint( $battery_level );
	}

	return apply_filters( 'wp_tesla_vehicle_get_battery_level', $battery_level, $vehicle_id, $user_id );
}

/**
 * Get the battery level for a vehicle.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @return float
 */
function get_estimated_range( $vehicle_id, $user_id = 0 ) {

	$est_battery_range = get_charge_state( $vehicle_id, $user_id, [ 'field' => 'est_battery_range' ] );

	if ( false !== $est_battery_range ) {
		$est_battery_range = floor( floatval( $est_battery_range ) );
	}

	return apply_filters( 'wp_tesla_vehicle_get_estimated_range', $est_battery_range, $vehicle_id, $user_id );
}

/**
 * Syncs charge state from the API to the post meta. Wakes up the vehicle
 * to get the current charge state.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @return void
 */
function sync_charge_state( $vehicle_id, $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$vehicle = get_existing_vehicle( $vehicle_id, $user_id );

	if ( ! empty( $vehicle ) ) {

		if ( wakeup( $vehicle_id, $user_id ) ) {
			$api_response = API\charge_state( $vehicle_id, $user_id );

			if ( ! empty( $api_response ) && is_object( $api_response ) && isset( $api_response->response ) ) {
				update_post_meta( $vehicle->ID, get_charge_state_key(), wp_json_encode( $api_response->response ) );
				update_post_meta( $vehicle->ID, get_charge_state_updated_key(), time() );
			}
		}
	}
}

/**
 * Wakes up a vehicle.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @param  array  $args       Additonal args.
 * @return bool
 */
function wakeup( $vehicle_id, $user_id = 0, $args = [] ) {

	$args = wp_parse_args(
		$args,
		[
			'show_cli_lines' => false,
		]
	);

	$cli = true === $args['show_cli_lines'];

	// Number of times we try to wakeup the vehicle.
	$max_tries  = apply_filters( 'wp_tesla_wakeup_max_tries', 5 );

	// Number of seconds to wait between wakeup tries in seconds.
	$sleep_time = apply_filters( 'wp_tesla_wakeup_sleep_between_tries', 3 );

	$online = false;

	$tries = 1;

	while ( ! $online && $tries <= $max_tries ) {

		if ( $cli ) {
			\WP_CLI::line(
				sprintf(
					'Attempting wakeup of %1$s, try #%2$d of %3$d...',
					$vehicle_id,
					$tries,
					$max_tries
				)
			);
		}

		$api_response = API\wakeup( $vehicle_id, $user_id );

		if ( ! empty( $api_response ) && is_object( $api_response ) && isset( $api_response->response ) ) {

			$response = wp_parse_args(
				(array) $api_response->response,
				[
					'state' => '',
				]
			);

			if ( $cli ) {

				if ( 'online' === $response['state'] ) {
					\WP_CLI::success(
						sprintf(
							'%1$s',
							$response['state']
						)
					);
				} else {
					\WP_CLI::warning(
						sprintf(
							'%1$s',
							$response['state']
						)
					);
				}
			}

			$online = 'online' === $response['state'];
		}

		if ( $online ) {
			break;
		}

		$tries++;

		if ( $tries <= $max_tries ) {

			if ( $cli ) {
				\WP_CLI::line(
					sprintf(
						'Sleeping for %1$d seconds...',
						$sleep_time
					)
				);
			}

			sleep( $sleep_time );
		}
	}

	return $online;
}

/**
 * Updates or creates a Tesla vehicle post
 *
 * @param string $vehicle_id The Tesla vehicle ID.
 * @param int    $user_id    The WP user ID.
 * @return void
 */
function maybe_create_sync_crons( $vehicle_id, $user_id ) {

	$args = [ $vehicle_id, $user_id ];

	$hooks = [
		'wp_tesla_sync_vehicle_charge_state',
	];

	foreach ( $hooks as $hook ) {
		$scheduled = wp_next_scheduled( $hook, $args );

		if ( empty( $scheduled ) ) {
			wp_schedule_event( time() + MINUTE_IN_SECONDS, 'hourly', $hook, $args );
		}
	}
}
