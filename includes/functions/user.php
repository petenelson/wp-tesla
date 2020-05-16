<?php
/**
 * User-specific functionality.
 *
 * @package WP Tesla
 */

namespace WPTesla\User;

use \WPTesla\API;

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
	add_action( 'wp_tesla_display_login_form', $n( 'display_login_form' ) );
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
		'connected' => false,
	];

	$token = \WPTesla\API\get_token( $user_id );

	if ( ! empty( $token ) ) {
		$results['connected'] = true;
	}

	return apply_filters( __FUNCTION__, $results, $user_id );
}

/**
 * Login/authenticates the WP account with the Tesla username and password.
 *
 * @param  int    $user_id  The WordPress user ID.
 * @param  string $username The username.
 * @param  string $password The password.
 * @return array
 */
function connect_user_account( $user_id, $username, $password ) {

	$results = [];

	return $results;
}

/**
 * Gets the menu slug for the settings page.
 *
 * @return string
 */
function get_settings_menu_slug() {
	return apply_filters( __FUNCTION__, \WPTesla\PostTypes\Tesla\get_post_type_name() . '-settings' );
}

/**
 * Adds a top-level menu page.
 *
 * @return void
 */
function add_tesla_settings_menu() {
	add_submenu_page(
		'edit.php?post_type=' . \WPTesla\PostTypes\Tesla\get_post_type_name(),
		__( 'My Tesla Account', 'wp-tesla' ),
		__( 'My Tesla Account', 'wp-tesla' ),
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
	?>

		<div class="wrap">

			<h1><?php esc_html_e( 'My Tesla Account', 'wp-tesla' ); ?></h1>

			<?php if ( ! is_the_user_connected() ) : ?>
				<?php do_action( 'wp_tesla_display_login_form' ); ?>
			<?php else : ?>
				<?php
				// Temp code for testing.
				$vehicles = \WPTesla\API\list_vehicles();

				echo '<pre>';
				echo wp_json_encode( $vehicles, JSON_PRETTY_PRINT );
				echo '</pre>';

				?>
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
	wp_enqueue_script( 'wp-api-fetch' );

	$user = wp_get_current_user();

	$user->user_email = 'pete@petenelson.com';
	?>
	<form method="post" id="wp-tesla-login-form">
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
