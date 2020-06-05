<?php
/**
 * Block editor functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\Blocks;

use \WPTesla\PostTypes\Tesla;
use \WPTesla\Vehicle;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_filter( 'block_categories', $n( 'update_block_categories' ), 10, 2 );
	add_filter( 'init', $n( 'register_blocks' ) );
	add_filter( 'wp_tesla_localize_admin_data', $n( 'localize_block_data' ) );

	add_shortcode( 'wp_tesla_battery_level', $n( 'shortcode_battery_level' ) );
	add_shortcode( 'wp_tesla_estimated_range', $n( 'shortcode_estimated_range' ) );
	add_shortcode( 'wp_tesla_charge_last_updated', $n( 'shortcode_charge_last_updated' ) );
}

/**
 * Updates block categories.
 *
 * @param  array   $categories List of categories.
 * @param  WP_Post $post       The post.
 * @return array
 */
function update_block_categories( $categories, $post ) {

	if ( ! empty( $post ) && Tesla\get_post_type_name() === $post->post_type ) {

		$category = [
			'slug'  => 'wp-tesla',
			'title' => __( 'Tesla', 'wp-tesla' ),
			'icon'  => null,
		];

		$categories = array_merge( $categories, [ $category ] );
	}

	return $categories;
}

/**
 * Gets a list of blocks.
 *
 * @return array
 */
function get_blocks() {

	$blocks = [
		'battery_level'   => 'wp-tesla/battery-level',
		'estimated_range' => 'wp-tesla/estimated-range',
	];

	// You can adjust the block name, but not the keys.
	$blocks = apply_filters( 'wp_tesla_block_names', $blocks );

	return $blocks;
}

/**
 * Gets the block name for a specific block.
 *
 * @param string $block The block name (battery_level, estimated_range, etc).
 * @return string
 */
function get_block_name( $block ) {
	$blocks = get_blocks();
	return $blocks[ $block ];
}

/**
 * Localizes block data for the editor.
 *
 * @param  array $data Localized data.
 * @return array
 */
function localize_block_data( $data ) {

	$post_id = get_the_ID();

	if ( ! empty( $post_id ) && Tesla\get_post_type_name() === get_post_type( get_the_ID() ) ) {

		$vehicle    = get_post( $post_id );
		$user_id    = $vehicle->post_author;
		$vehicle_id = Vehicle\get_vehicle_id( $vehicle->ID );

		$battery_level  = Vehicle\get_battery_level( $vehicle_id, $user_id );

		if ( false !== $battery_level ) {
			$battery_level = $battery_level . '%';
		}

		$estimated_range = Vehicle\get_estimated_range( $vehicle_id, $user_id );

		if ( false !== $estimated_range ) {
			// We'll look into km later.
			$estimated_range = $estimated_range . 'mi';
		}

		$data['blocks'] = get_blocks();

		$data['currentVehicle'] = [
			'batteryLevel'   => $battery_level,
			'estimatedRange' => $estimated_range,
		];
	}

	return $data;
}

/**
 * Registers the various blocks.
 *
 * @return void
 */
function register_blocks() {

	register_block_type(
		get_block_name( 'battery_level' ),
		[
			'render_callback' => '\WPTesla\Blocks\render_battery_level',
		]
	);

	register_block_type(
		get_block_name( 'estimated_range' ),
		[
			'render_callback' => '\WPTesla\Blocks\render_estimated_range',
		]
	);
}

/**
 * Renders the battery level.
 *
 * @param array $attributes Block attributes.
 * @return void
 */
function render_battery_level( $attributes = [] ) {

	$attributes = wp_parse_args(
		$attributes,
		[
			'className' => '',
		]
	);

	$output  = '';
	$results = Vehicle\get_the_vehicle();

	if ( ! empty( $results ) ) {

		$battery_level = Vehicle\get_battery_level( $results['vehicle_id'], $results['user_id'] );

		if ( false !== $battery_level ) {
			$battery_level = $battery_level . '%';
		}

		$output = sprintf(
			'<span class="%2$s">%1$s</span>',
			esc_html( $battery_level ),
			sanitize_html_class( $attributes['className'] )
		);
	}

	$output = apply_filters( 'wp_tesla_render_block_battery_level', $output, $attributes );

	echo wp_kses_post( $output );
}

/**
 * Renders the estimated range.
 *
 * @param array $attributes Block attributes.
 * @return void
 */
function render_estimated_range( $attributes = [] ) {

	$attributes = wp_parse_args(
		$attributes,
		[
			'className' => '',
		]
	);

	$output  = '';
	$results = Vehicle\get_the_vehicle();

	if ( ! empty( $results ) ) {

		$estimated_range = Vehicle\get_estimated_range( $results['vehicle_id'], $results['user_id'] );

		if ( false !== $estimated_range ) {
			// We'll look into km later.
			$estimated_range = $estimated_range . 'mi';
		}

		$output = sprintf(
			'<span class="%2$s">%1$s</span>',
			esc_html( $estimated_range ),
			sanitize_html_class( $attributes['className'] )
		);
	}

	$output = apply_filters( 'wp_tesla_render_block_estimated_range', $output, $attributes );

	echo wp_kses_post( $output );
}

/**
 * Gets the battery level shortcode content.
 *
 * @return string
 */
function shortcode_battery_level() {
	ob_start();
	render_battery_level();
	return ob_get_clean();
}

/**
 * Gets the estimated_range shortcode content.
 *
 * @return string
 */
function shortcode_estimated_range() {
	ob_start();
	render_estimated_range();
	return ob_get_clean();
}

/**
 * Gets the wp_tesla_charge_last_updated shortcode content.
 *
 * @return string
 */
function shortcode_charge_last_updated() {

	$vehicle = Vehicle\get_the_vehicle();

	if ( ! empty( $vehicle ) ) {

		$date_format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$last_updated_ts = absint( get_post_meta( $vehicle['post']->ID, Vehicle\get_charge_state_updated_key(), true ) );

		$last_updated = new \DateTime( 'now', wp_timezone() );
		$last_updated->setTimestamp( $last_updated_ts );

		return esc_html( $last_updated->format( $date_format ) );
	}
}
