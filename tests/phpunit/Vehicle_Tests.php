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

		$this->assertNotEmpty( $vehicle );
		$this->assertSame( $post_id, $vehicle->ID );

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
		];

		$vehicle = Vehicle\sync_vehicle( '33015387032628850', $this->admin->ID, $vehicle_data );

		$this->assertNotEmpty( $vehicle );
		$this->assertSame( 'Sulaco', $vehicle->post_title );
		$this->assertSame( 'sulaco', $vehicle->post_name );

		$post_id = $vehicle->ID;

		$vehicle_data = [
			'display_name' => 'Serenity',
		];

		$vehicle = Vehicle\sync_vehicle( '33015387032628850', $this->admin->ID, $vehicle_data );

		$this->assertNotEmpty( $vehicle );
		$this->assertSame( 'Serenity', $vehicle->post_title );
		$this->assertSame( 'serenity', $vehicle->post_name );
		$this->assertSame( $post_id, $vehicle->ID );
	}
}
