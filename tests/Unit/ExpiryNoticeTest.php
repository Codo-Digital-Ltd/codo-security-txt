<?php
/**
 * Tests for ExpiryNotice.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Actions;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Admin\ExpiryNotice;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Admin\ExpiryNotice
 */
final class ExpiryNoticeTest extends TestCase {

	private function output( $screen_id = 'dashboard' ) {
		Functions\when( 'get_current_screen' )->justReturn( null === $screen_id ? null : (object) array( 'id' => $screen_id ) );
		ob_start();
		( new ExpiryNotice( new Status( new Settings() ) ) )->render();
		return ob_get_clean();
	}

	public function test_it_registers_on_admin_notices() {
		$notice = new ExpiryNotice( new Status( new Settings() ) );
		Actions\expectAdded( 'admin_notices' )->once()->with( array( $notice, 'render' ) );

		$notice->register();
		$this->assertTrue( true );
	}

	public function test_an_expired_file_shows_an_error() {
		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );

		$html = $this->output();

		$this->assertStringContainsString( 'notice-error', $html );
		$this->assertStringContainsString( 'has expired', $html );
		$this->assertStringContainsString( 'page=codo-security-txt', $html );
	}

	public function test_an_expiring_file_shows_a_warning() {
		$this->store( array( 'expires' => gmdate( 'Y-m-d\T23:59:59\Z', time() + 10 * DAY_IN_SECONDS ) ) );

		$html = $this->output( 'plugins' );

		$this->assertStringContainsString( 'notice-warning', $html );
		$this->assertStringContainsString( 'expires on', $html );
	}

	public function test_a_healthy_file_shows_nothing() {
		$this->store( array( 'expires' => '2099-01-01T00:00:00Z' ) );

		$this->assertSame( '', $this->output() );
	}

	public function test_it_stays_off_other_screens() {
		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );

		$this->assertSame( '', $this->output( 'edit-post' ) );
		$this->assertSame( '', $this->output( null ) );
	}

	public function test_it_stays_hidden_from_users_without_the_capability() {
		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );
		Functions\when( 'current_user_can' )->justReturn( false );

		$this->assertSame( '', $this->output() );
	}
}
