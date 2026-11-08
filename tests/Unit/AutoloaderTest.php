<?php
/**
 * Tests for Autoloader.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Autoloader;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Autoloader
 */
final class AutoloaderTest extends TestCase {

	public function test_it_ignores_other_namespaces() {
		$this->assertFalse( Autoloader::load( 'Other\\Thing' ) );
	}

	public function test_it_rejects_unsafe_class_names() {
		$this->assertFalse( Autoloader::load( 'CodoDigital\\SecurityTxt\\..\\..\\evil' ) );
		$this->assertFalse( Autoloader::load( 'CodoDigital\\SecurityTxt\\Bad-Name' ) );
	}

	public function test_it_returns_false_for_a_missing_file() {
		$this->assertFalse( Autoloader::load( 'CodoDigital\\SecurityTxt\\Missing\\Nothing' ) );
	}

	public function test_it_loads_a_real_class() {
		Autoloader::register( dirname( __DIR__, 2 ) . '/src' );

		$this->assertTrue( Autoloader::load( 'CodoDigital\\SecurityTxt\\Document\\Expiry' ) );
		$this->assertTrue( class_exists( 'CodoDigital\\SecurityTxt\\Document\\Expiry' ) );
	}
}
