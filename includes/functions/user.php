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

use function \WPTesla\Helpers\filter_strip_all_tags;

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
	add_action( 'admin_action_wp_tesla_logout', $n( 'maybe_logout_user' ) );
	add_action( 'admin_action_wp_tesla_refresh_token', $n( 'maybe_refresh_token' ) );
	add_action( 'admin_action_wp_tesla_sync_vehicles', $n( 'maybe_sync_vehicles' ) );
	add_action( 'admin_action_wp_tesla_connect_to_account', $n( 'connect_to_account' ) );

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

	$url = \WPTesla\API\get_login_form_url();

	?>

		<form method="get" action="<?php echo esc_url( admin_url( 'admin.php' ) ); ?>">
			<input type="hidden" name="action" value="wp_tesla_connect_to_account" />
			<input type="hidden" name="wp_tesla_nonce" value="<?php echo esc_attr( wp_create_nonce( 'connect_to_account' ) ); ?>" />

			<strong><?php esc_html_e( 'Logging in to your Tesla account and syncing vehicles into WordPress is a two-step process.', 'wp-tesla' ); ?></strong>

			<ol>
				<li>
					<p>
						<?php esc_html_e( 'First, you will need to login to your Tesla account. This opens a new window, and after entering your valid name and password, you will see a Page Not Found message. This is expected. Copy the URL of this page once you have logged in.', 'wp-tesla' ); ?>
					</p>

					<a href="<?php echo esc_url( $url ); ?>" target="_blank" class="button button-primary" id="wp-tesla-login-to-account"><?php esc_html_e( 'Login to your Tesla account', 'wp-tesla'); ?></a>
				</li>
				<li>
					<p>
						<?php esc_html_e( 'Paste the URL from the valid login into the field below. This will validate the access code and allow WordPress to connect the vehicles on your account.', 'wp-tesla'); ?>
						<br/>
						<input type="text" class="large-text" name="login_auth_url" id="wp-tesla-login-auth-url" />
					</p>
					<button class="button button-primary" disabled id="wp-tesla-connect-to-vehicles"><?php esc_html_e( 'Connect to my Tesla vehicles', 'wp-tesla'); ?></a>
				</li>
			</ol>
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

	$sync_vehicles_url = add_query_arg(
		[
			'action'         => 'wp_tesla_sync_vehicles',
			'wp_tesla_nonce' => rawurlencode( wp_create_nonce( 'sync_vehicles' ) ),
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

	<a href="<?php echo esc_url( $sync_vehicles_url ); ?>" class="button">
		<?php esc_html_e( 'Sync Vehicles', 'wp-tesla' ); ?>
	</a>

	<?php
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
			'wp_tesla_nonce' => filter_strip_all_tags(),
		]
	);

	if ( wp_verify_nonce( $get['wp_tesla_nonce'], 'logout' ) ) {

		// Logout the user.
		logout();

		// Redirect to the account/settings page.
		wp_safe_redirect( get_account_page_url() );
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
			'wp_tesla_nonce' => filter_strip_all_tags(),
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
			'wp_tesla_nonce' => filter_strip_all_tags(),
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

/**
 * Gets the URL for the account page.
 *
 * @return string
 */
function get_account_page_url() {
	$url = add_query_arg(
		[
			'post_type' => rawurlencode( \WPTesla\PostTypes\Tesla\get_post_type_name() ),
			'page'      => rawurlencode( get_settings_menu_slug() ),
		],
		admin_url( 'edit.php' )
	);

	return $url;
}

/**
 * Parses the authorization URL code and connects to the Tesla account.
 *
 * @return void
 */
function connect_to_account() {

	$get = filter_var_array(
		$_GET,
		[
			'wp_tesla_nonce' => filter_strip_all_tags(),
			'login_auth_url' => FILTER_SANITIZE_URL,
		]
	);

	if ( wp_verify_nonce( $get['wp_tesla_nonce'], 'connect_to_account' ) && ! empty( $get['login_auth_url'] ) ) {

		$url = wp_parse_url( $get['login_auth_url'] );

		wp_parse_str( $url['query'], $params );

		if ( isset( $params['code'] ) && ! empty( $params['code'] ) ) {
			API\authenticate_v3( $params['code'] );
		}
	}

	// Redirect to the account/settings page.
	wp_safe_redirect( get_account_page_url() );
	exit;
}
