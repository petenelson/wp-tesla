<?php // phpcs:ignore

namespace WPTesla\Tests;

use \WPTesla\Taxonomies\OptionCode;

/**
 * Option Code taxonomy tests.
 */
class OptionCode_Tests extends \WP_UnitTestCase {

	/**
	 * Tests get_option_code().
	 *
	 * @return void
	 * @group  option-code
	 */
	public function test_get_option_code() {

		// Option codes are currently disabled.
		$this->assertTrue( true );
		return;

		// Get some option codes.
		$term = OptionCode\get_option_code( 'MDL3' );

		$this->assertInstanceOf( '\WP_Term', $term );
		$this->assertSame( strtolower( 'MDL3' ), $term->slug );
		$this->assertSame( 'Model 3', $term->name );
		$this->assertSame( 'This vehicle is a Model 3', $term->description );
		$this->assertSame( 'MDL3', get_term_meta( $term->term_id, 'wp_tesla_option_code', true ) );

		$term = OptionCode\get_option_code( 'RENA' );

		$this->assertInstanceOf( '\WP_Term', $term );
		$this->assertSame( strtolower( 'RENA' ), $term->slug );
		$this->assertSame( 'Region: North America', $term->name );
		$this->assertEmpty( $term->description );

		$term = OptionCode\get_option_code( 'RF3G' );

		$this->assertInstanceOf( '\WP_Term', $term );
		$this->assertSame( strtolower( 'RF3G' ), $term->slug );
		$this->assertSame( 'Model 3 Glass Roof', $term->name );
		$this->assertSame( 'RF3G', get_term_meta( $term->term_id, 'wp_tesla_option_code', true ) );

		$wp_term = get_term_by( 'slug', 'RF3G', OptionCode\get_taxonomy_name() );
		$this->assertSame( $term->term_id, $wp_term->term_id );
	}
}
