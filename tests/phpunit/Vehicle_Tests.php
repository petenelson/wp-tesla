<?php // phpcs:ignore

namespace WPTesla\Tests;

use \WPTesla\Vehicle;
use \WPTesla\PostTypes\Tesla;

/**
 * Vehicle tests.
 */
class Vehicle_Tests extends \WP_UnitTestCase {

	/**
	 * Setup the unit test.
	 */
	public function setUp() {
		parent::setUp();

		$this->admin = $this->factory->user->create_and_get( array( 'role' => 'administrator' ) );
	}

	/**
	 * Tests get_existing_vehicle().
	 *
	 * @return void
	 * @group  vehicle
	 */
	public function test_get_existing_vehicle() {

		$vehicle_data = [
			'display_name' => 'Sulaco',
		];

		$postarr = [
			'post_type'   => Tesla\get_post_type_name(),
			'post_status' => 'publish',
			'post_title'  => $vehicle_data['display_name'],
			'post_name'   => sanitize_title( $vehicle_data['display_name'] ),
			'post_author' => $this->admin->ID,
		];

		$post_id = wp_insert_post( $postarr );

		$this->assertGreaterThan( 0, $post_id );

		update_post_meta( $post_id, 'wp_tesla_vehicle_id_33015387032628850', '33015387032628850' );

		$vehicle = Vehicle\get_existing_vehicle( '33015387032628850', $this->admin->ID );

		$this->assertInstanceOf( '\WP_Post', $vehicle );
		$this->assertSame( $post_id, $vehicle->ID );

		$this->assertSame( '33015387032628850', Vehicle\get_vehicle_id( $vehicle->ID ) );

		wp_delete_post( $vehicle->ID, true );
	}

	/**
	 * Tests sync_vehicle().
	 *
	 * @return void
	 * @group  vehicle
	 */
	public function test_sync_vehicle() {

		$vehicle_data = [
			'display_name' => 'Sulaco',
			'vin'          => '12345',
		];

		$vehicle_id = '33015387032628850';

		$vehicle = Vehicle\sync_vehicle( $vehicle_id, $this->admin->ID, $vehicle_data );

		$this->assertInstanceOf( '\WP_Post', $vehicle );
		$this->assertSame( 'Sulaco', $vehicle->post_title );
		$this->assertSame( 'sulaco', $vehicle->post_name );
		$this->assertSame( 'publish', $vehicle->post_status );
		$this->assertSame( Tesla\get_post_type_name(), $vehicle->post_type );
		$this->assertSame( $vehicle_id, get_post_meta( $vehicle->ID, Vehicle\get_vehicle_id_meta_prefix() . $vehicle_id, true ) );
		$this->assertSame( $vehicle_id, Vehicle\get_vehicle_id( $vehicle->ID ) );
		$this->assertSame( '12345', Vehicle\get_vin( $vehicle ) );

		$post_id = $vehicle->ID;

		$vehicle = Vehicle\get_existing_vehicle( $vehicle_id, $this->admin->ID );

		$this->assertInstanceOf( '\WP_Post', $vehicle );
		$this->assertSame( $post_id, $vehicle->ID );

		$vehicle_data = [
			'display_name' => 'Serenity',
			'vin'          => '45678',
		];

		$vehicle = Vehicle\sync_vehicle( $vehicle_id, $this->admin->ID, $vehicle_data );

		$this->assertInstanceOf( '\WP_Post', $vehicle );
		$this->assertSame( 'Serenity', $vehicle->post_title );
		$this->assertSame( 'serenity', $vehicle->post_name );
		$this->assertSame( $post_id, $vehicle->ID );
		$this->assertSame( $vehicle_id, Vehicle\get_vehicle_id( $vehicle->ID ) );
		$this->assertSame( '45678', Vehicle\get_vin( $vehicle ) );

		wp_delete_post( $vehicle->ID, true );
		wp_delete_post( $post_id, true );
	}
}
