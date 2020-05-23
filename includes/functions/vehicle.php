<?php
/**
 * Vehicle-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\Vehicle;

use \WPTesla\API;
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

	$query_args = [
		'post_type'              => Tesla\get_post_type_name(),
		'posts_per_page'         => 1,
		'update_post_meta_cache' => false,
		'update_term_meta_cache' => false,
		'author'                 => $user_id,
		'meta_key'               => 'wp_tesla_vehicle_id_' . $vehicle_id,
		'meta_value'             => $vehicle_id,
	];

	$query = new \WP_Query( $query_args );

	$vehicle = ! empty( $query->posts ) ? $query->posts[0] : false;

	if ( ! empty( $vehicle ) ) {
		$vehicle->vehicle_id = $vehicle_id;
	}

	return apply_filters( 'wp_tesla_get_existing_vehicle', $vehicle );
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
 * Updates or creates a Tesla vehicle post
 *
 * @param string $vehicle_id   The Tesla vehicle ID.
 * @param int    $user_id      The WP user ID.
 * @param array  $vehicle_data The vehicle data from the API.
 *
 * @return WP_Post
 */
function sync_vehicle( $vehicle_id, $user_id, $vehicle_data ) {

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
			update_post_meta( $post_id, 'wp_tesla_vehicle_id_' . $vehicle_id, $vehicle_id );
		}

		$vehicle = get_post( $post_id );
	}

	if ( ! empty( $vehicle ) ) {

		$postarr = [
			'ID'          => $vehicle->ID,
			'post_title'  => $vehicle_data['display_name'],
			'post_name'   => sanitize_title( $vehicle_data['display_name'] ),
		];

		wp_update_post( $postarr );

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

	return apply_filters( 'wp_tesla_sync_vehicle', $vehicle, $vehicle_id, $user_id );
}

/**
 * How often should a vehicle charge state be synced in seconds, defaults
 * to one hour.
 *
 * @return int
 */
function charge_sync_interval() {
	return apply_filters( 'wp_tesla_charge_sync_interval', HOUR_IN_SECONDS * 1 );
}

/**
 * Get the charge state data for a vehicle.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @param  int    $user_id    The user ID.
 * @return array
 */
function get_charge_state( $vehicle_id, $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	$vehicle = get_existing_vehicle( $vehicle_id, $user_id );

	$charge_data = false;

	if ( ! empty( $vehicle ) ) {

		$charge_data = get_post_meta( $vehicle->ID, get_charge_state_key(), true );

		if ( empty( $charge_data ) ) {
			sync_charge_data( $vehicle );
			$charge_data = get_post_meta( $vehicle->ID, get_charge_state_key(), true );
		}

		if ( ! empty( $charge_data ) ) {
			$charge_data = json_decode( $charge_data );
			if ( is_object( $charge_data ) && isset( $charge_data->response ) ) {
				$charge_data = (array) $charge_data->response;
			}
		}
	}

	return apply_filters( 'wp_tesla_vehicle_get_charge_state', $charge_data, $vehicle_id, $user_id );
}

/**
 * Syncs charge state from the API to the post meta.
 *
 * @param  WP_Post $vehicle The vehicle post object.
 * @param  int     $user_id The user ID.
 * @return void
 */
function sync_charge_state( $vehicle, $user_id = 0 ) {

	$user_id = empty( $user_id ) ? get_current_user_id() : $user_id;

	if ( ! empty( $vehicle->vehicle_id ) ) {

		// TODO implement wakeup.
		$api_response = API\charge_state( $vehicle->vehicle_id, $user_id );
		var_dump( $api_response );

		if ( ! empty( $api_response ) && is_object( $api_response ) && isset( $api_response->response ) ) {
			update_post_meta( $vehicle->ID, get_charge_state_key(), wp_json_encode( $api_response->response ) );
			update_post_meta( $vehicle->ID, get_charge_state_updated_key(), time() );
		}
	}
}
