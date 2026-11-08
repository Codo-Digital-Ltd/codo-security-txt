<?php
/**
 * Tests for Endpoint.
 *
 * @package CodoDigital\SecurityTxt\Tests
 */

namespace CodoDigital\SecurityTxt\Tests\Unit;

use Brain\Monkey\Filters;
use Brain\Monkey\Functions;
use CodoDigital\SecurityTxt\Document\Builder;
use CodoDigital\SecurityTxt\Http\Endpoint;
use CodoDigital\SecurityTxt\Settings\Settings;
use CodoDigital\SecurityTxt\Status;
use CodoDigital\SecurityTxt\Tests\TestCase;

/**
 * Endpoint subclass that records instead of sending.
 */
class RecordingEndpoint extends Endpoint {

	/**
	 * Responses passed to send().
	 *
	 * @var array<int, array<string, mixed>>
	 */
	public $sent = array();

	/**
	 * Record.
	 *
	 * @param array<string, mixed> $response Response.
	 * @return void
	 */
	protected function send( array $response ) {
		$this->sent[] = $response;
	}
}

/**
 * @covers \CodoDigital\SecurityTxt\Http\Endpoint
 */
final class EndpointTest extends TestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->store( array( 'expires' => '2099-01-01T23:59:59Z' ) );
	}

	protected function tearDown(): void {
		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
		parent::tearDown();
	}

	private function endpoint() {
		$settings = new Settings();
		return new RecordingEndpoint( $settings, new Status( $settings ), new Builder() );
	}

	public function test_it_serves_the_well_known_path() {
		$response = $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' );

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( 'text/plain; charset=utf-8', $response['headers']['Content-Type'] );
		$this->assertSame( 'nosniff', $response['headers']['X-Content-Type-Options'] );
		$this->assertStringContainsString( 'Contact: mailto:security@example.com', $response['body'] );
		$this->assertStringContainsString( 'Canonical: https://www.example.com/.well-known/security.txt', $response['body'] );
		$this->assertFalse( $response['head'] );
	}

	public function test_head_requests_have_no_body_flag() {
		$this->assertTrue( $this->endpoint()->resolve( 'HEAD', '/.well-known/security.txt' )['head'] );
	}

	public function test_other_methods_and_paths_are_ignored() {
		$endpoint = $this->endpoint();

		$this->assertNull( $endpoint->resolve( 'POST', '/.well-known/security.txt' ) );
		$this->assertNull( $endpoint->resolve( 'GET', '/.well-known/other.txt' ) );
		$this->assertNull( $endpoint->resolve( 'GET', '/' ) );
		$this->assertNull( $endpoint->resolve( 'GET', '/.well-known/security.txt/' ) );
	}

	public function test_the_legacy_path_follows_its_setting() {
		$this->assertSame( 200, $this->endpoint()->resolve( 'GET', '/security.txt' )['status'] );

		$this->store( array( 'expires' => '2099-01-01T23:59:59Z', 'legacy_path' => false ) );
		$this->assertNull( $this->endpoint()->resolve( 'GET', '/security.txt' ) );
		$this->assertNotNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );
	}

	public function test_unpublished_states_are_not_served() {
		$this->store( array( 'enabled' => false ) );
		$this->assertNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );

		$this->store( array( 'contacts' => array() ) );
		$this->assertNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );
	}

	public function test_an_expired_file_is_still_served() {
		$this->store( array( 'expires' => '2020-01-01T00:00:00Z' ) );

		$this->assertSame( 200, $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' )['status'] );
	}

	public function test_subdirectory_subsites_do_not_serve_the_file() {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_subdomain_install' )->justReturn( false );
		Functions\when( 'is_main_site' )->justReturn( false );

		$this->assertNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );
	}

	public function test_the_main_site_and_subdomain_subsites_serve_the_file() {
		Functions\when( 'is_multisite' )->justReturn( true );
		Functions\when( 'is_subdomain_install' )->justReturn( false );
		Functions\when( 'is_main_site' )->justReturn( true );
		$this->assertNotNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );

		Functions\when( 'is_subdomain_install' )->justReturn( true );
		Functions\when( 'is_main_site' )->justReturn( false );
		$this->assertNotNull( $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' ) );
	}

	public function test_headers_and_body_can_be_filtered() {
		Filters\expectApplied( 'codo_security_txt_headers' )->once()->andReturn( array( 'X-Test' => '1' ) );
		Filters\expectApplied( 'codo_security_txt_body' )->once()->andReturn( 'custom' );

		$response = $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' );

		$this->assertSame( array( 'X-Test' => '1' ), $response['headers'] );
		$this->assertSame( 'custom', $response['body'] );
	}

	public function test_a_bad_header_filter_result_is_ignored() {
		Filters\expectApplied( 'codo_security_txt_headers' )->once()->andReturn( 'nope' );

		$this->assertSame( array(), $this->endpoint()->resolve( 'GET', '/.well-known/security.txt' )['headers'] );
	}

	public function test_maybe_handle_sends_a_matching_request() {
		$_SERVER['REQUEST_METHOD'] = 'get';
		$_SERVER['REQUEST_URI']    = '/.well-known/security.txt?x=1';
		$endpoint                  = $this->endpoint();

		$endpoint->maybe_handle();

		$this->assertCount( 1, $endpoint->sent );
	}

	public function test_maybe_handle_ignores_other_requests() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = '/hello/';
		$endpoint                  = $this->endpoint();
		$endpoint->maybe_handle();

		unset( $_SERVER['REQUEST_METHOD'], $_SERVER['REQUEST_URI'] );
		$endpoint->maybe_handle();

		$this->assertSame( array(), $endpoint->sent );
	}

	public function test_maybe_handle_survives_an_unparseable_uri() {
		$_SERVER['REQUEST_METHOD'] = 'GET';
		$_SERVER['REQUEST_URI']    = 'http:///';
		$endpoint                  = $this->endpoint();
		$endpoint->maybe_handle();

		$this->assertSame( array(), $endpoint->sent );
	}
}
