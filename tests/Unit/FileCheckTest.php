<?php
/**
 * Tests for FileCheck.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Server\FileCheck
 */
final class FileCheckTest extends TestCase {

	/**
	 * Temp dir.
	 *
	 * @var string
	 */
	private $dir;

	protected function setUp(): void {
		parent::setUp();
		$this->dir = sys_get_temp_dir() . '/codo-sectxt-' . uniqid();
		mkdir( $this->dir . '/.well-known', 0777, true );
	}

	protected function tearDown(): void {
		foreach ( array( '/.well-known/security.txt', '/security.txt' ) as $file ) {
			if ( is_file( $this->dir . $file ) ) {
				unlink( $this->dir . $file );
			}
		}
		rmdir( $this->dir . '/.well-known' );
		rmdir( $this->dir );
		parent::tearDown();
	}

	public function test_no_files_means_no_conflicts() {
		$this->assertSame( array(), ( new FileCheck( array( $this->dir, '', $this->dir . '/' ) ) )->conflicts() );
	}

	public function test_physical_files_are_reported_once() {
		file_put_contents( $this->dir . '/.well-known/security.txt', 'x' );
		file_put_contents( $this->dir . '/security.txt', 'x' );

		$check = new FileCheck( array( $this->dir, $this->dir . '/' ) );

		$this->assertSame( array( '/.well-known/security.txt', '/security.txt' ), $check->conflicts() );
	}

	public function test_a_root_found_twice_does_not_duplicate_paths() {
		file_put_contents( $this->dir . '/security.txt', 'x' );

		$check = new FileCheck( array( $this->dir, $this->dir . '/.well-known/..' ) );

		$this->assertSame( array( '/security.txt' ), $check->conflicts() );
	}
}
