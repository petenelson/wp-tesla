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
$updated         = false;
$now             = time();

$date_format     = get_option( 'date_format' ) . ' ' . get_option( 'time_format' );

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

	$last_updated_ts = absint( get_post_meta( $vehicle->ID, Vehicle\get_charge_state_updated_key(), true ) );

	$last_updated = new \DateTime( 'now', wp_timezone() );
	$last_updated->setTimestamp( $last_updated_ts );

	$next_update = new \DateTime( 'now', wp_timezone() );
	$next_update->setTimestamp( $last_updated_ts + Vehicle\get_charge_sync_interval() );
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
		<li>
			<?php esc_html_e( 'Last Updated', 'wp-tesla' ); ?>: <?php echo esc_html( $last_updated->format( $date_format ) ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Next Update', 'wp-tesla' ); ?>: <?php echo esc_html( $next_update->format( $date_format ) ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Last Updated TS', 'wp-tesla' ); ?>: <?php echo esc_html( $last_updated_ts ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Now', 'wp-tesla' ); ?>: <?php echo esc_html( $now ); ?>
		</li>
		<li>
			<?php esc_html_e( 'Diff', 'wp-tesla' ); ?>: <?php echo esc_html( $now - $last_updated_ts ); ?>
		</li>
	</ul>

</div>
