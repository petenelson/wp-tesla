<?php
/**
 * Vehicle post type for this plugin.
 *
 * @package WP Tesla
 */

namespace WPTesla\PostTypes\Tesla;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	// TODO prevent add new.
	add_action( 'init', $n( 'register' ) );
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
