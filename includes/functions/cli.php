<?php
/**
 * WP-CLI commands.
 *
 * @package WP Tesla
 */

namespace WPTesla\CLI;

use \WPTesla\Vehicle;
use \WPTesla\API;

if ( ! defined( 'WP_CLI' ) || ( defined( 'WP_CLI' ) && ! WP_CLI ) ) {
	return;
}

/**
 * Create a namespaced function.
 *
 * @param  string $function The function name,
 * @return string
 */
function n( $function ) {
	return __NAMESPACE__ . "\\$function";
};

// phpcs:ignore
/**
 * Wakes up a vehicle. Requires the --user parameter.
 *
 * ## OPTIONS
 *
 * <vehicle_id>
 * The vehicle ID (not the post ID).
 *
 * ## EXAMPLES
 *
 *     wp --user=admin wp-tesla wakeup 33015387032628850
 *
 * @synopsis <vehicle_id>
 */
function wakeup( $args, $assoc_args = [] ) {

	$user_id = get_current_user_id();

	if ( empty( $user_id ) ) {
		\WP_CLI::error( 'Invalid --user parameter' );
	}

	$vehicle = Vehicle\get_existing_vehicle( $args[0], $user_id );

	if ( ! is_a( $vehicle, '\WP_Post' ) ) {
		\WP_CLI::error( 'Invalid vehicle ID' );
	}

	Vehicle\wakeup( $args[0], $user_id, [ 'show_cli_lines' => true ] );

}
\WP_CLI::add_command( 'wp-tesla wakeup', n( 'wakeup' ) );

// phpcs:ignore
/**
 * Gets a list of vehicles from the Tesla API. Requires the --user parameter of a user that
 * has been authenticated with their tesla.com account
 *
 * ## EXAMPLES
 *
 *     wp --user=admin wp-tesla vehicles
 */
function vehicles( $args, $assoc_args = [] ) {

	$user_id = get_current_user_id();

	if ( empty( $user_id ) ) {
		\WP_CLI::error( 'Invalid --user parameter' );
	}

	$results = API\vehicles( $user_id );

	var_dump( $results );
}
\WP_CLI::add_command( 'wp-tesla vehicles', n( 'vehicles' ) );
