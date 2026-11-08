<?php
/**
 * Tests for Status.
 *
 * @package CodoDigital\SecurityTxt
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * @covers \CodoDigital\SecurityTxt\Status
 */
final class StatusTest extends TestCase {

	private function state( array $overrides = array() ) {
		$this->store( $overrides );
		return ( new Status( new Settings() ) )->evaluate( $this->now );
	}

	public function test_nothing_stored_means_disabled() {
		$state = ( new Status( new Settings() ) )->evaluate( $this->now );

		$this->assertSame( Status::DISABLED, $state['state'] );
		$this->assertNull( $state['expires'] );
		$this->assertFalse( $state['signed'] );
	}

	public function test_disabled_setting() {
		$this->assertSame( Status::DISABLED, $this->state( array( 'enabled' => false ) )['state'] );
	}

	public function test_evaluate_defaults_to_the_current_time() {
		$this->store( array( 'expires' => gmdate( 'Y-m-d\TH:i:s\Z', time() + 90 * DAY_IN_SECONDS ) ) );

		$this->assertSame( Status::OK, ( new Status( new Settings() ) )->evaluate()['state'] );
	}

	public function test_missing_contacts_or_expiry_is_invalid() {
		$this->assertSame( Status::INVALID, $this->state( array( 'contacts' => array() ) )['state'] );
		$this->assertSame( Status::INVALID, $this->state( array( 'expires' => '' ) )['state'] );
		$this->assertSame( Status::INVALID, $this->state( array( 'expires' => 'soon' ) )['state'] );
	}

	public function test_a_far_expiry_is_ok() {
		$state = $this->state();

		$this->assertSame( Status::OK, $state['state'] );
		$this->assertSame( 205, $state['days_left'] );
		$this->assertSame( 1811894399, $state['expires'] );
	}

	public function test_expiring_within_thirty_days() {
		$state = $this->state( array( 'expires' => '2026-12-01T23:59:59Z' ) );

		$this->assertSame( Status::EXPIRING, $state['state'] );
		$this->assertSame( 23, $state['days_left'] );
	}

	public function test_a_past_expiry_is_expired() {
		$this->assertSame( Status::EXPIRED, $this->state( array( 'expires' => '2026-11-01T00:00:00Z' ) )['state'] );
		$this->assertSame( Status::EXPIRED, $this->state( array( 'expires' => '2026-11-08T12:00:00Z' ) )['state'] );
	}

	public function test_a_signed_message_supplies_the_expiry() {
		$ok      = $this->state(
			array(
				'contacts' => array(),
				'expires'  => '',
				'signed'   => $this->signed( '2027-06-01T23:59:59Z' ),
			)
		);
		$expired = $this->state( array( 'signed' => $this->signed( '2026-01-01T00:00:00Z' ) ) );
		$broken  = $this->state( array( 'signed' => 'not signed' ) );

		$this->assertSame( Status::OK, $ok['state'] );
		$this->assertTrue( $ok['signed'] );
		$this->assertSame( Status::EXPIRED, $expired['state'] );
		$this->assertSame( Status::INVALID, $broken['state'] );
	}

	public function test_published_states() {
		$this->assertTrue( Status::is_published( Status::OK ) );
		$this->assertTrue( Status::is_published( Status::EXPIRING ) );
		$this->assertTrue( Status::is_published( Status::EXPIRED ) );
		$this->assertFalse( Status::is_published( Status::INVALID ) );
		$this->assertFalse( Status::is_published( Status::DISABLED ) );
	}

	public function test_file_and_canonical_urls_use_the_host_root() {
		$status = new Status( new Settings() );

		$this->assertSame( 'https://www.example.com/.well-known/security.txt', $status->file_url() );
		$this->assertSame( 'https://www.example.com/.well-known/security.txt', $status->canonical_url() );
	}

	public function test_a_site_in_a_subdirectory_or_on_a_port_still_points_at_the_root() {
		Functions\when( 'home_url' )->justReturn( 'https://example.com:8443/blog' );

		$this->assertSame( 'https://example.com:8443/.well-known/security.txt', ( new Status( new Settings() ) )->file_url() );
	}

	public function test_an_http_site_has_no_canonical_url() {
		Functions\when( 'home_url' )->justReturn( 'http://example.com' );
		$status = new Status( new Settings() );

		$this->assertSame( 'http://example.com/.well-known/security.txt', $status->file_url() );
		$this->assertSame( '', $status->canonical_url() );
	}

	public function test_an_unparseable_home_url_gives_no_url() {
		Functions\when( 'home_url' )->justReturn( '/just-a-path' );

		$this->assertSame( '', ( new Status( new Settings() ) )->file_url() );
	}
}
