<?php
/**
 * Option Code taxonomy for this plugin.
 * NOTE: This functionality is currently disabled.
 *
 * @package WP Tesla
 */

namespace WPTesla\Taxonomies\OptionCode;

/**
 * Default setup routine
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};
}

/**
 * Gets the Tesla option code taxonomy name.
 *
 * @return string
 */
function get_taxonomy_name() {
	return apply_filters( 'wp_tesla_get_option_code_name', 'wp-tesla-option-code' );
}

/**
 * Gets the args for registering the option codee taxonomy.
 *
 * @return array
 */
function get_taxonomy_args() {

	$labels = [
		'name'                  => _x( 'Option Codes', 'Taxonomy Option Codes', 'wp-tesla' ),
		'singular_name'         => _x( 'Option Code', 'Taxonomy Option Code', 'wp-tesla' ),
		'search_items'          => __( 'Search Option Codes', 'wp-tesla' ),
		'popular_items'         => __( 'Popular Option Codes', 'wp-tesla' ),
		'all_items'             => __( 'All Option Codes', 'wp-tesla' ),
		'parent_item'           => __( 'Parent Option Code', 'wp-tesla' ),
		'parent_item_colon'     => __( 'Parent Option Code', 'wp-tesla' ),
		'edit_item'             => __( 'Edit Option Code', 'wp-tesla' ),
		'update_item'           => __( 'Update Option Code', 'wp-tesla' ),
		'add_new_item'          => __( 'Add New Option Code', 'wp-tesla' ),
		'new_item_name'         => __( 'New Option Code Name', 'wp-tesla' ),
		'add_or_remove_items'   => __( 'Add or remove Option Codes', 'wp-tesla' ),
		'choose_from_most_used' => __( 'Choose from most used Option Codes', 'wp-tesla' ),
		'menu_name'             => __( 'Option Codes', 'wp-tesla' ),
	];

	$args = array(
		'labels'            => $labels,
		'public'            => true,
		'show_in_nav_menus' => true,
		'show_admin_column' => false,
		'hierarchical'      => false,
		'show_tagcloud'     => true,
		'show_ui'           => true,
		'query_var'         => true,
		'rewrite'           => true,
		'query_var'         => true,
		'capabilities'      => array(),
	);

	return apply_filters( 'wp_tesla_get_option_code_args', $args );
}

/**
 * Registers the taxonomy.
 *
 * @return void
 */
function register() {
	$object_types = [
		\WPTesla\PostTypes\Tesla\get_post_type_name(),
	];

	register_taxonomy( get_taxonomy_name(), $object_types, get_taxonomy_args() );
}

/**
 * Gets an option code by the slug. Creates the option code if it doesn't
 * exist.
 *
 * @param  string $slug The option code slug from the API.
 * @return WP_Term
 */
function get_option_code( $slug ) {

	// Option codes from the API may not be reliable.
	return false;

	$taxonomy = get_taxonomy_name();

	$term = get_term_by( 'slug', $slug, $taxonomy );
	if ( is_a( $term, '\WP_Term' ) ) {
		return apply_filters( 'wp_tesla_get_option_code', $term, $slug );
	}

	$codes_file = WP_TESLA_INC . 'taxonomies/option-codes.json';
	$codes      = [];

	if ( file_exists( $codes_file ) ) {
		$codes = file_get_contents( $codes_file );
		$codes = json_decode( $codes );
	}

	$term = false;

	foreach ( $codes as $code ) {

		$code = wp_parse_args(
			(array) $code,
			[
				'slug'        => '',
				'name'        => '',
				'description' => '',
			]
		);

		if ( $slug === $code['slug'] ) {

			$term_data = wp_insert_term(
				$code['name'],
				$taxonomy,
				[
					'slug'        => $code['slug'],
					'description' => $code['description'],
				]
			);

			if ( is_array( $term_data ) && isset( $term_data['term_id'] ) ) {
				$term = get_term( $term_data['term_id'], $taxonomy );

				update_term_meta( $term->term_id, 'wp_tesla_option_code', $code['slug'] );
			}
		}
	}

	return apply_filters( 'wp_tesla_get_option_code', $term, $slug );
}
