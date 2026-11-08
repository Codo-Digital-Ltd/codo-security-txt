<?php
/**
 * Tests for SiteHealth.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Filters;
use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Health\SiteHealth;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Server\FileCheck;
use CodoDigital\SecurityTxt\Server\Reachability;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Health\SiteHealth
 */
final class SiteHealthTest extends TestCase {

	/**
	 * Conflicts to report.
	 *
	 * @var string[]
	 */
	private $conflicts = array();

	/**
	 * Reachability mock.
	 *
	 * @var \Mockery\MockInterface
	 */
	private $reach;

	protected function setUp(): void {
		parent::setUp();
		$this->conflicts = array();
	}

	private function health() {
		$settings    = new Settings();
		$status      = new Status( $settings );
		$file_check  = \Mockery::mock( FileCheck::class );
		$file_check->shouldReceive( 'conflicts' )->andReturnUsing(
			function () {
				return $this->conflicts;
			}
		);
		$this->reach = \Mockery::mock( Reachability::class );

		return new SiteHealth( $status, new Endpoint( $settings, $status, new Builder() ), $file_check, $this->reach );
	}

	public function test_it_registers_a_direct_test() {
		$tests = $this->health()->add_test( array( 'direct' => array() ) );

		$this->assertArrayHasKey( 'codo_security_txt', $tests['direct'] );
		$this->assertIsCallable( $tests['direct']['codo_security_txt']['test'] );
	}

	public function test_register_hooks_the_filter() {
		Filters\expectAdded( 'site_status_tests' )->once();

		$this->health()->register();
		$this->assertTrue( true );
	}

	public function test_disabled_is_recommended() {
		$result = $this->health()->run();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertSame( 'codo_security_txt', $result['test'] );
	}

	public function test_invalid_and_expired_are_critical() {
		$this->store( array( 'contacts' => array() ) );
		$this->assertSame( 'critical', $this->health()->run()['status'] );

		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );
		$this->assertSame( 'critical', $this->health()->run()['status'] );
	}

	public function test_expiring_is_recommended() {
		$this->store( array( 'expires' => gmdate( 'Y-m-d\T23:59:59\Z', time() + 5 * DAY_IN_SECONDS ) ) );

		$this->assertSame( 'recommended', $this->health()->run()['status'] );
	}

	public function test_a_physical_file_is_recommended_against() {
		$this->store( array( 'expires' => '2099-01-01T00:00:00Z' ) );
		$this->conflicts = array( '/security.txt', '/.well-known/security.txt' );

		$result = $this->health()->run();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( '/security.txt, /.well-known/security.txt', $result['description'] );
	}

	public function test_an_unreachable_file_is_recommended() {
		$this->store( array( 'expires' => '2099-01-01T00:00:00Z' ) );
		$health = $this->health();
		$this->reach->shouldReceive( 'cached' )->once()->andReturn(
			array(
				'status'  => 'error',
				'message' => 'Cannot fetch',
			)
		);

		$result = $health->run();

		$this->assertSame( 'recommended', $result['status'] );
		$this->assertStringContainsString( 'Cannot fetch', $result['description'] );
	}

	public function test_a_cold_cache_runs_the_check() {
		$this->store( array( 'expires' => '2099-01-01T00:00:00Z' ) );
		$health = $this->health();
		$this->reach->shouldReceive( 'cached' )->once()->andReturn( null );
		$this->reach->shouldReceive( 'run_and_store' )->once()->andReturn(
			array(
				'status'  => 'ok',
				'message' => 'Fine',
			)
		);

		$this->assertSame( 'good', $health->run()['status'] );
	}

	public function test_a_reachable_file_is_good() {
		$this->store( array( 'expires' => '2099-01-01T00:00:00Z' ) );
		$health = $this->health();
		$this->reach->shouldReceive( 'cached' )->once()->andReturn(
			array(
				'status'  => 'ok',
				'message' => 'Fine',
			)
		);

		$result = $health->run();

		$this->assertSame( 'good', $result['status'] );
		$this->assertStringContainsString( 'page=codo-security-txt', $result['actions'] );
	}
}
