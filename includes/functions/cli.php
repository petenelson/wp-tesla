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
 * Authenticate an account and store the necessary tokens. Requires the --user parameter.
 *
 * ## OPTIONS
 *
 * <email>
 * The Tesla account email address.
 *
 * <password>
 * The Tesla account password. This is not stored anywhere in WordPress
 *
 * ## EXAMPLES
 *
 *     wp --user=admin wp-tesla authenticate elon@tesla.com mypassword
 *
 * @synopsis <email> <password>
 */
function authenticate( $args, $assoc_args = [] ) {

	$user_id = get_current_user_id();

	if ( empty( $user_id ) ) {
		\WP_CLI::error( 'Invalid --user parameter' );
	}

	$results = API\authenticate_v3( $args[0], $args[1], $user_id );

	var_dump( $results ); die();













}
\WP_CLI::add_command( 'wp-tesla authenticate', n( 'authenticate' ) );
