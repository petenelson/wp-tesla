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
 * Determines if the current user is connect.
 *
 * @param string $vehicle_id The Tesla vehicle ID.
 * @param int    $user_id    The WP user ID.
 * @return WP_Post
 */
function get_existing_vehicle( $vehicle_id, $user_id ) {

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

	return apply_filters( 'wp_tesla_get_existing_vehicle', $vehicle );
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
