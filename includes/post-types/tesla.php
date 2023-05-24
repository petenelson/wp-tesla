<?php
/**
 * Vehicle post type for this plugin.
 *
 * @package WP Tesla
 */

namespace WPTesla\PostTypes\Tesla;

use WPTesla\Vehicle;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// TODO implement caps.
	add_action( 'init', $n( 'register' ) );

	add_action( 'manage_' . get_post_type_name() . '_posts_columns', $n( 'update_table_columns' ) );
	add_action( 'manage_' . get_post_type_name() . '_posts_custom_column', $n( 'handle_columns' ), 10, 2 );

	// TODO add row actions for sync, turn off quick edit.
	add_action( 'wp_tesla_vehicle_do_custom_column_vehicle_id', $n( 'column_vehicle_id' ) );
	add_action( 'wp_tesla_vehicle_do_custom_column_battery_level', $n( 'column_battery_level' ) );
	add_action( 'wp_tesla_vehicle_do_custom_column_ideal_range', $n( 'column_ideal_range' ) );
	add_action( 'wp_tesla_vehicle_do_custom_column_vin', $n( 'column_vin' ) );
}

/**
 * Gets the Tesla post type name.
 *
 * @return string
 */
function get_post_type_name() {
	return apply_filters( 'wp_tesla_get_vehicle_post_type_name', 'wp-tesla' );
}

/**
 * Gets the post type args for registering the post type.
 *
 * @return array
 */
function get_post_type_args() {

	$labels = [
		'name'               => __( 'Teslas', 'wp-tesla' ),
		'singular_name'      => __( 'Tesla', 'wp-tesla' ),
		'add_new'            => _x( 'Add New Tesla', 'wp-tesla', 'wp-tesla' ),
		'add_new_item'       => __( 'Add New Tesla', 'wp-tesla' ),
		'edit_item'          => __( 'Edit Tesla', 'wp-tesla' ),
		'new_item'           => __( 'New Tesla', 'wp-tesla' ),
		'view_item'          => __( 'View Tesla', 'wp-tesla' ),
		'search_items'       => __( 'Search Teslas', 'wp-tesla' ),
		'not_found'          => __( 'No Teslas found', 'wp-tesla' ),
		'not_found_in_trash' => __( 'No Teslas found in Trash', 'wp-tesla' ),
		'parent_item_colon'  => __( 'Parent Tesla:', 'wp-tesla' ),
		'menu_name'          => __( 'Teslas', 'wp-tesla' ),
	];

	$args = [
		'labels'              => $labels,
		'hierarchical'        => false,
		'description'         => __( 'List of Tesla vehicles', 'wp-tesla' ),
		'taxonomies'          => [],
		'public'              => true,
		'show_ui'             => true,
		'show_in_menu'        => true,
		'show_in_admin_bar'   => true,
		'show_in_rest'        => true,
		'menu_position'       => null,
		'menu_icon'           => null,
		'show_in_nav_menus'   => true,
		'publicly_queryable'  => true,
		'exclude_from_search' => false,
		'has_archive'         => true,
		'query_var'           => true,
		'can_export'          => true,
		'rewrite'             => [
			'slug' => 'tesla',
		],
		'capability_type'     => 'post',
		'supports'            => [
			'title',
			'editor',
			'author',
			'thumbnail',
			'excerpt',
		],
	];

	return apply_filters( 'wp_tesla_get_vehicle_post_type_args', $args );
}

/**
 * Registers the post types.
 *
 * @return void
 */
function register() {
	register_post_type( get_post_type_name(), get_post_type_args() );
}

/**
 * Gets a list of custom columns and labels.
 *
 * @return array
 */
function get_custom_columns() {

	$columns = [
		'battery_level' => __( 'Battery', 'wp-tesla' ),
		'ideal_range'   => __( 'Range', 'wp-tesla' ),
		'vehicle_id'    => __( 'ID', 'wp-tesla' ),
		'vin'           => __( 'VIN', 'wp-tesla' ),
	];

	return apply_filters( 'wp_tesla_vehicle_get_custom_columns', $columns );
}

/**
 * Updates the columns for the list of vehicles in admin.
 *
 * @param array $columns List of columns.
 */
function update_table_columns( $columns ) {
	$columns = array_merge( $columns, get_custom_columns() );

	if ( isset( $columns['title'] ) ) {
		$columns['title'] = __( 'Name' );
	}

	if ( isset( $columns['author'] ) ) {
		unset( $columns['author'] );
	}

	return $columns;
}

/**
 * Handles the custom columns.
 *
 * @param  string $column  The column name.
 * @param  int    $post_id The post ID.
 * @return void
 */
function handle_columns( $column, $post_id ) {

	$vehicle_id = false;

	if ( in_array( $column, array_keys( get_custom_columns() ), true ) ) {
		$vehicle_id = Vehicle\get_vehicle_id( $post_id );
	}

	if ( ! empty( $vehicle_id ) ) {
		do_action( 'wp_tesla_vehicle_do_custom_column_' . $column, $vehicle_id );
	}
}

/**
 * Outputs the vehicle ID value.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @return void
 */
function column_vehicle_id( $vehicle_id ) {
	echo esc_html( $vehicle_id );
}

/**
 * Outputs the vehicle battery level.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @return void
 */
function column_battery_level( $vehicle_id ) {
	$battery_level = Vehicle\get_battery_level( $vehicle_id );

	if ( false !== $battery_level ) {
		$battery_level = $battery_level . '%';
	}

	echo esc_html( $battery_level );
}

/**
 * Outputs the vehicle estimated range.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @return void
 */
function column_ideal_range( $vehicle_id ) {
	$est_range = Vehicle\get_ideal_range( $vehicle_id );

	if ( false !== $est_range ) {
		// We'll look into km later.
		$est_range = $est_range . 'mi';
	}

	echo esc_html( $est_range );
}

/**
 * Outputs the VIN.
 *
 * @param  string $vehicle_id The vehicle ID.
 * @return void
 */
function column_vin( $vehicle_id ) {
	echo esc_html( Vehicle\get_vin( $vehicle_id ) );
}
