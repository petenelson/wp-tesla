<?php
/**
 * Vehicle content template.
 *
 * @package WP Tesla
 */

use \WPTesla\Vehicle;

$vehicle_id      = false;
$battery_level   = false;
$estimated_range = false;

if ( is_singular( \WPTesla\PostTypes\Tesla\get_post_type_name() ) ) {

	$vehicle = get_queried_object();

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
}

?>

<div class="wp-tesla wp-tesla-vehicle">

	<ul>
		<li>
			<?php esc_html_e( 'Battery Level', 'wp-tesla' ); ?>: <?php echo esc_html( $battery_level ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Estimated Range', 'wp-tesla' ); ?>: <?php echo esc_html( $estimated_range ); ?>
		</li>
	</ul>

</div>
