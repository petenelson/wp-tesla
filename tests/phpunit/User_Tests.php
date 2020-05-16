<?php // phpcs:ignore

namespace WPTesla\Tests;

use WPTesla\User;

/**
 * User tests.
 */
class User_Tests extends \WP_UnitTestCase {

	/**
	 * Tests get_account_status().
	 *
	 * @return void
	 * @group  user
	 */
	public function test_get_account_status() {

		// Not a logged-in user.
		$status = User\get_account_status();

		$this->assertIsArray( $status );
		$this->assertFalse( $status['connected'] );
		$this->assertEmpty( $status['token'] );
	}

	/**
	 * Tests the is_the_user_connected() function.
	 *
	 * @return void
	 * @group  user
	 */
	public function test_is_the_user_connected() {

		// Not a logged-in user.
		$connected = User\is_the_user_connected();

		$this->assertFalse( $connected );
	}
}
