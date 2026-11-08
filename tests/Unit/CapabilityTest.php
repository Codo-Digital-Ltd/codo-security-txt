<?php
/**
 * Tests for Capability.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Filters;
use CodoDigital\SecurityTxt\Admin\Capability;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Admin\Capability
 */
final class CapabilityTest extends TestCase {

	public function test_the_default_is_manage_options() {
		$this->assertSame( 'manage_options', Capability::site() );
	}

	public function test_it_can_be_filtered() {
		Filters\expectApplied( 'codo_security_txt_capability' )->once()->andReturn( 'edit_pages' );

		$this->assertSame( 'edit_pages', Capability::site() );
	}

	public function test_a_bad_filter_value_falls_back() {
		Filters\expectApplied( 'codo_security_txt_capability' )->once()->andReturn( '' );

		$this->assertSame( 'manage_options', Capability::site() );
	}
}
