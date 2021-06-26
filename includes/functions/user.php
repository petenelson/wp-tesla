<?php
/**
 * User-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\User;

use \WPTesla\API;
use \WPTesla\Helpers;
use \WPTesla\Vehicle;

/**
 * Set up theme defaults and register supported WordPress features.
 *
 * @return void
 */
function setup() {
	$n = function( $function ) {
		return __NAMESPACE__ . "\\$function";
	};

	add_action( 'admin_menu', $n( 'add_tesla_settings_menu' ) );
	add_action( 'admin_post_wp_tesla_login', $n( 'maybe_login_user' ) );
	add_action( 'admin_action_wp_tesla_logout', $n( 'maybe_logout_user' ) );
	add_action( 'admin_action_wp_tesla_refresh_token', $n( 'maybe_refresh_token' ) );
	add_action( 'admin_action_wp_tesla_sync_vehicles', $n( 'maybe_sync_vehicles' ) );

	// Custom actions.
	add_action( 'wp_tesla_display_login_form', $n( 'display_login_form' ) );
	add_action( 'wp_tesla_display_logged_in_template', $n( 'display_logged_in_template' ) );
	add_action( 'wp_tesla_user_logged_out', '\WPTesla\API\invalidate_api_cache' );
}

/**
 * Determines if the current user is connect.
 *
 * @return boolean
 */
function is_the_user_connected() {
	$status = get_account_status();
	return $status['connected'];
}

/**
 * Gets the user's account status.
 *
 * @param  int $user_id The user ID.
 * @return array
 */
function get_account_status( $user_id = null ) {

	// TODO add a cap check to see if current user can access other accounts.
	$results = [
		'connected'       => false,
		'token'           => false,
		'refresh'         => false,
		'created'         => false,
		'expires'         => false,
		'created_display' => false,
		'expires_display' => false,
	];

	$token = \WPTesla\API\get_token( $user_id );

	if ( ! empty( $token ) ) {
		$results['connected'] = true;
		$results['token']     = $token;
		$results['refresh']   = get_user_option( API\get_refresh_token_key() );
		$results['created']   = absint( get_user_option( API\get_created_key() ) );
		$results['expires']   = absint( get_user_option( API\get_expires_key() ) );

		$format = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

		$created = new \DateTime( 'now', wp_timezone() );
		$created->setTimestamp( $results['created'] );

		$expires = new \DateTime( 'now', wp_timezone() );
		$expires->setTimestamp( $results['expires'] );

		$results['created_display'] = $created->format( $format );
		$results['expires_display'] = $expires->format( $format );
	}

	return apply_filters( 'wp_tesla_user_get_account_status', $results, $user_id );
}

/**
 * Gets the menu slug for the settings page.
 *
 * @return string
 */
function get_settings_menu_slug() {
	return apply_filters( 'wp_tesla_user_get_settings_menu_slug', \WPTesla\PostTypes\Tesla\get_post_type_name() . '-settings' );
}

/**
 * Adds a top-level menu page.
 *
 * @return void
 */
function add_tesla_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . \WPTesla\PostTypes\Tesla\get_post_type_name(),
		esc_html__( 'My Tesla Account', 'wp-tesla' ),
		esc_html__( 'My Tesla Account', 'wp-tesla' ),
		'edit_posts',
		get_settings_menu_slug(),
		__NAMESPACE__ . '\display_settings_page'
	);
}

/**
 * Displays the settings page.
 *
 * @return void
 */
function display_settings_page() {

	$connected = is_the_user_connected();

	$classes = [
		'wrap',
		'wp-tesla-settings',
	];

	if ( $connected ) {
		$classes[] = 'wp-tesla-user-connected';
	} else {
		$classes[] = 'wp-tesla-user-not-connected';
	}

	?>

		<div class="<?php Helpers\output_css_classes( $classes ); ?>">

			<h1><?php esc_html_e( 'My Tesla Account', 'wp-tesla' ); ?></h1>

			<?php if ( ! is_the_user_connected() ) : ?>
				<?php do_action( 'wp_tesla_display_login_form' ); ?>
			<?php else : ?>
				<?php do_action( 'wp_tesla_display_logged_in_template' ); ?>
			<?php endif; ?>
		</div>

	<?php
}

/**
 * Displays the login form.
 *
 * @return void
 */
function display_login_form() {
	$user = wp_get_current_user();

	// Makes it easier for local testing.
	$email    = defined( 'WP_TESLA_EMAIL' ) ? WP_TESLA_EMAIL : $user->user_email;
	$password = defined( 'WP_TESLA_PASSWORD' ) ? WP_TESLA_PASSWORD : '';

	?>
	<form method="post" id="wp-tesla-login-form" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
		<input type="hidden" name="wp_tesla_nonce" value="<?php echo esc_attr( wp_create_nonce( 'login' ) ); ?>" />
		<input type="hidden" name="action" value="wp_tesla_login" />
		<table class="form-table">
			<tbody>
				<tr>
					<th>
						<label for="tesla-user-email"><?php esc_html_e( 'Email Address', 'wp-tesla' ); ?></label>
					</th>
					<td>
						<input type="text" name="email" id="tesla-user-email" value="<?php echo esc_attr( $email ); ?>" class="regular-text" placeholder="<?php esc_html_e( 'tesla@example.com', 'wp-tesla' ); ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="tesla-user-password"><?php esc_html_e( 'Password', 'wp-tesla' ); ?></label>
					</th>
					<td>
						<input type="password" name="password" id="tesla-user-password" value="<?php echo esc_attr( $password ); ?>" class="regular-text" placeholder="<?php esc_attr_e( 'Password', 'wp-tesla' ); ?>" />
						<p class="description">
							<?php esc_html_e( 'Your username and password are not stored anywhere on this site and are only sent directly to the Tesla login service.', 'wp-tesla' ); ?>
						</p>
						<?php submit_button( __( 'Login', 'wp-tesla' ) ); ?>
					</td>
				</tr>
			</tbody>
		</table>
	</form>
	<?php
}

/**
 * Displays the template when a user is logged in.
 *
 * @return void
 */
function display_logged_in_template() {

	$logout_url = add_query_arg(
		[
			'action'         => 'wp_tesla_logout',
			'wp_tesla_nonce' => rawurlencode( wp_create_nonce( 'logout' ) ),
		],
		admin_url( 'admin.php' )
	);

	$refresh_url = add_query_arg(
		[
			'action'         => 'wp_tesla_refresh_token',
			'wp_tesla_nonce' => rawurlencode( wp_create_nonce( 'refresh' ) ),
		],
		admin_url( 'admin.php' )
	);

	$status = get_account_status();

	?>

	<table class="form-table" role="presentation">
		<tbody>
			<tr>
				<th>
					<?php esc_html_e( 'User Token', 'wp-tesla' ); ?>
				</th>
				<td>
					<?php echo esc_html( $status['token'] ); ?>
				</td>
			</tr>

			<tr>
				<th>
					<?php esc_html_e( 'Refresh Token', 'wp-tesla' ); ?>
				</th>
				<td>
					<?php echo esc_html( $status['refresh'] ); ?>
				</td>
			</tr>

			<tr>
				<th>
					<?php esc_html_e( 'Created At', 'wp-tesla' ); ?>
				</th>
				<td>
					<?php echo esc_html( $status['created_display'] ); ?>
				</td>
			</tr>

			<tr>
				<th>
					<?php esc_html_e( 'Expires At', 'wp-tesla' ); ?>
				</th>
				<td>
					<?php echo esc_html( $status['expires_display'] ); ?>
				</td>
			</tr>

		</tbody>

	</table>

	<a href="<?php echo esc_url( $logout_url ); ?>" class="button button-primary">
		<?php esc_html_e( 'Logout', 'wp-tesla' ); ?>
	</a>

	<a href="<?php echo esc_url( $refresh_url ); ?>" class="button">
		<?php esc_html_e( 'Refresh Token', 'wp-tesla' ); ?>
	</a>

	<?php
}

/**
 * Admin action hook to login the user.
 *
 * @return void
 */
function maybe_login_user() {

	$post = filter_var_array(
		$_POST, // phpcs:ignore
		[
			'wp_tesla_nonce' => FILTER_SANITIZE_STRING,
			'email'          => FILTER_SANITIZE_EMAIL,
			'password'       => FILTER_UNSAFE_RAW,
		]
	);

	if ( wp_verify_nonce( $post['wp_tesla_nonce'], 'login' ) ) {

		$results = \WPTesla\API\authenticate_v3(
			trim( $post['email'] ),
			trim( $post['password'] ),
			get_current_user_id()
		);

		if ( $results['authenticated'] ) {

			// Redirect to sync vehicles.
			$url = add_query_arg(
				[
					'action'         => 'wp_tesla_sync_vehicles',
					'wp_tesla_nonce' => rawurlencode( wp_create_nonce( 'sync_vehicles' ) ),
				],
				admin_url( 'admin.php' )
			);
		} else {
			$url = add_query_arg(
				[
					'post_type' => rawurlencode( \WPTesla\PostTypes\Tesla\get_post_type_name() ),
					'page'      => rawurlencode( get_settings_menu_slug() ),
					'error'     => 'invalid-login',
				],
				admin_url( 'edit.php' )
			);
		}

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Admin action hook to logout the user.
 *
 * @return void
 */
function maybe_logout_user() {

	$get = filter_var_array(
		$_GET,
		[
			'wp_tesla_nonce' => FILTER_SANITIZE_STRING,
		]
	);

	if ( wp_verify_nonce( $get['wp_tesla_nonce'], 'logout' ) ) {

		// Logout the user.
		logout();

		// Redirect to the account/settings page.
		$url = add_query_arg(
			[
				'post_type' => rawurlencode( \WPTesla\PostTypes\Tesla\get_post_type_name() ),
				'page'      => rawurlencode( get_settings_menu_slug() ),
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Admin action hook to sync the vehicles from the API to WP posts.
 *
 * @return void
 */
function maybe_sync_vehicles() {

	$get = filter_var_array(
		$_GET,
		[
			'wp_tesla_nonce' => FILTER_SANITIZE_STRING,
		]
	);

	if ( wp_verify_nonce( $get['wp_tesla_nonce'], 'sync_vehicles' ) ) {

		$vehicles = API\vehicles( get_current_user_id() );

		if ( is_array( $vehicles ) ) {
			foreach ( $vehicles as $vehicle_data ) {
				Vehicle\sync_vehicle( $vehicle_data['id_s'], get_current_user_id(), $vehicle_data );
			}
		}

		// Redirect to the vehicles page.
		$url = add_query_arg(
			[
				'post_type' => rawurlencode( \WPTesla\PostTypes\Tesla\get_post_type_name() ),
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Admin action hook to refresh the user token.
 *
 * @return void
 */
function maybe_refresh_token() {

	$get = filter_var_array(
		$_GET,
		[
			'wp_tesla_nonce' => FILTER_SANITIZE_STRING,
		]
	);

	if ( wp_verify_nonce( $get['wp_tesla_nonce'], 'refresh' ) ) {

		API\refresh_token( get_current_user_id() );

		$url = add_query_arg(
			[
				'post_type' => rawurlencode( \WPTesla\PostTypes\Tesla\get_post_type_name() ),
				'page'      => rawurlencode( get_settings_menu_slug() ),
			],
			admin_url( 'edit.php' )
		);

		wp_safe_redirect( $url );
		exit;
	}
}

/**
 * Deletes the user tokens and essentially logs them out of the Tesla API.
 *
 * @param  integer $user_id The user ID, defaults to current user.
 * @return void
 */
function logout( $user_id = 0 ) {

	if ( empty( $user_id ) ) {
		$user_id = get_current_user_id();
	}

	if ( empty( $user_id ) ) {
		return;
	}

	$token = get_user_option( API\get_token_key(), $user_id );

	$keys = [
		API\get_token_key(),
		API\get_refresh_token_key(),
		API\get_created_key(),
		API\get_expires_key(),
	];

	foreach ( $keys as $key ) {
		delete_user_option( $user_id, $key );
	}

	API\revoke_token( $token );

	do_action( 'wp_tesla_user_logged_out', $user_id );
}
