<?php
/**
 * User-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\User;

use \WPTesla\API;
use \WPTesla\Helpers;

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
		'connected' => false,
		'token'     => false,
	];

	$token = \WPTesla\API\get_token( $user_id );

	if ( ! empty( $token ) ) {
		$results['connected'] = true;
		$results['token']     = $token;
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

	$user->user_email = 'pete@petenelson.com';

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
						<input type="text" name="email" id="tesla-user-email" value="<?php echo esc_attr( $user->user_email ); ?>" class="regular-text" placeholder="<?php esc_html_e( 'tesla@example.com', 'wp-tesla' ); ?>" />
					</td>
				</tr>
				<tr>
					<th>
						<label for="tesla-user-password"><?php esc_html_e( 'Password', 'wp-tesla' ); ?></label>
					</th>
					<td>
						<input type="password" name="password" id="tesla-user-password" value="" class="regular-text" placeholder="<?php esc_attr_e( 'Password', 'wp-tesla' ); ?>" />
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

	?>

	<?php
	// Temp code for testing.
	$vehicles = \WPTesla\API\vehicles();

	echo '<pre>';
	echo wp_json_encode( $vehicles, JSON_PRETTY_PRINT );
	echo '</pre>';

	?>

	<a href="<?php echo esc_url( $logout_url ); ?>" class="button button-primary">
		<?php esc_html_e( 'Logout', 'wp-tesla' ); ?>
	</a>

	<?php
}

/**
 * Admin actionhook to logout the user.
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

		$a = \WPTesla\API\authenticate(
			trim( $post['email'] ),
			trim( $post['password'] ),
			get_current_user_id()
		);

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
 * Admin actionhook to logout the user.
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

		logout();

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

	$keys = [
		API\get_token_key(),
		API\get_refresh_token_key(),
		API\get_expire_key(),
	];

	foreach ( $keys as $key ) {
		delete_user_option( $user_id, $key );
	}

	do_action( 'wp_tesla_user_logged_out', $user_id );
}
