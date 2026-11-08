<?php
/**
 * Tests for Settings.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Settings\Settings
 */
final class SettingsTest extends TestCase {

	public function test_defaults_are_returned_when_nothing_is_stored() {
		$this->assertSame( Settings::defaults(), ( new Settings() )->all() );
		$this->assertFalse( ( new Settings() )->all()['enabled'] );
	}

	public function test_stored_values_are_cast_to_the_default_types() {
		$this->options[ Settings::OPTION ] = array(
			'enabled'     => 1,
			'contacts'    => array( 'a', 2 ),
			'canonical'   => 'not an array',
			'expires'     => '2027-01-01T00:00:00Z',
			'policy'      => array( 'not a string' ),
			'unknown_key' => 'dropped',
		);

		$all = ( new Settings() )->all();

		$this->assertTrue( $all['enabled'] );
		$this->assertSame( array( 'a', '2' ), $all['contacts'] );
		$this->assertSame( array(), $all['canonical'] );
		$this->assertSame( '2027-01-01T00:00:00Z', $all['expires'] );
		$this->assertSame( '', $all['policy'] );
		$this->assertArrayNotHasKey( 'unknown_key', $all );
	}

	public function test_a_corrupt_option_falls_back_to_defaults() {
		$this->options[ Settings::OPTION ] = 'garbage';

		$this->assertSame( Settings::defaults(), ( new Settings() )->all() );
	}

	public function test_save_stores_only_known_keys() {
		$settings = new Settings();
		$settings->save( $this->settings( array( 'extra' => 'x' ) ) );

		$this->assertArrayNotHasKey( 'extra', $this->options[ Settings::OPTION ] );
		$this->assertSame( array( 'mailto:security@example.com' ), $settings->all()['contacts'] );
	}

	public function test_the_constant_does_not_disable_by_default() {
		$this->assertFalse( ( new Settings() )->disabled_by_constant() );
	}
}
